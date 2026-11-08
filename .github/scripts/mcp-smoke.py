"""Sign in to a running Shopmade site the way an MCP client does and call a tool.

    python3 mcp-smoke.py http://localhost:8080 owner@example.com PASSWORD

Registers an OAuth client (loopback callback, PKCE), signs in as the owner, approves the consent screen, swaps the
code for a token, then runs initialize, tools/list and pages_list over /mcp/agents. Exits non-zero on the first failure.
"""

import base64
import hashlib
import html
import http.cookiejar
import json
import re
import secrets
import sys
import urllib.error
import urllib.parse
import urllib.request

base, email, password = sys.argv[1].rstrip("/"), sys.argv[2], sys.argv[3]
redirect_uri = "http://127.0.0.1:53682/callback"


class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, *args, **kwargs):
        return None


cookies = http.cookiejar.CookieJar()
browser = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(cookies))
no_redirect = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(cookies), NoRedirect)


def call(opener, url, data=None, headers=None, method=None):
    request = urllib.request.Request(url, data=data, headers=headers or {}, method=method)
    try:
        response = opener.open(request, timeout=30)
    except urllib.error.HTTPError as error:
        response = error
    return response.status, dict(response.headers), response.read().decode()


def check(condition, message):
    if not condition:
        sys.exit(f"FAIL: {message}")
    print(f"ok: {message}")


def form_fields(page):
    return {html.unescape(name): html.unescape(value) for name, value in re.findall(r'<input type="hidden" name="([^"]+)" value="([^"]*)"', page)}


status, _, body = call(browser, f"{base}/.well-known/oauth-authorization-server")
check(status == 200, "authorization server metadata")
metadata = json.loads(body)
scope = " ".join(metadata.get("scopes_supported", []))

status, headers, _ = call(browser, f"{base}/mcp/agents", data=b"{}", headers={"Content-Type": "application/json"})
check(status == 401 and "resource_metadata" in headers.get("WWW-Authenticate", ""), "MCP asks for OAuth")

status, _, body = call(
    browser,
    metadata["registration_endpoint"],
    data=json.dumps({"client_name": "CI smoke", "redirect_uris": [redirect_uri], "token_endpoint_auth_method": "none"}).encode(),
    headers={"Content-Type": "application/json"},
)
check(status == 201, "dynamic client registration")
client_id = json.loads(body)["client_id"]

status, _, page = call(browser, f"{base}/login")
token = re.search(r'name="_token" value="([^"]+)"', page).group(1)
status, headers, _ = call(no_redirect, f"{base}/login", data=urllib.parse.urlencode({"_token": token, "email": email, "password": password}).encode())
location = headers.get("Location", "")
check(status == 302 and location.startswith(base) and not location.endswith("/login"), f"owner signs in (sent to {location})")

verifier = secrets.token_urlsafe(48)
challenge = base64.urlsafe_b64encode(hashlib.sha256(verifier.encode()).digest()).rstrip(b"=").decode()
state = secrets.token_urlsafe(16)
query = urllib.parse.urlencode({
    "response_type": "code",
    "client_id": client_id,
    "redirect_uri": redirect_uri,
    "scope": scope,
    "state": state,
    "code_challenge": challenge,
    "code_challenge_method": "S256",
    "resource": f"{base}/mcp/agents",
})
status, _, page = call(browser, f"{metadata['authorization_endpoint']}?{query}")
check(status == 200 and "Approve" in page, "consent screen")

fields = form_fields(page)
fields["decision"] = "approve"
status, headers, _ = call(no_redirect, metadata["authorization_endpoint"], data=urllib.parse.urlencode(fields).encode())
location = urllib.parse.urlparse(headers.get("Location", ""))
callback = urllib.parse.parse_qs(location.query)
check(status in (302, 303) and callback.get("state") == [state] and "code" in callback, "approval redirects to the loopback callback with a code")

status, _, body = call(
    browser,
    metadata["token_endpoint"],
    data=urllib.parse.urlencode({
        "grant_type": "authorization_code",
        "code": callback["code"][0],
        "redirect_uri": redirect_uri,
        "client_id": client_id,
        "code_verifier": verifier,
        "resource": f"{base}/mcp/agents",
    }).encode(),
)
check(status == 200, "code exchanged for a token")
access_token = json.loads(body)["access_token"]


def rpc(method, params, session=None, version=None):
    headers = {"Content-Type": "application/json", "Accept": "application/json, text/event-stream", "Authorization": f"Bearer {access_token}"}
    if session:
        headers["Mcp-Session-Id"] = session
    if version:
        headers["MCP-Protocol-Version"] = version
    message = {"jsonrpc": "2.0", "id": secrets.randbelow(10**6), "method": method, "params": params}
    status, response_headers, body = call(browser, f"{base}/mcp/agents", data=json.dumps(message).encode(), headers=headers)
    return status, {key.lower(): value for key, value in response_headers.items()}, json.loads(body) if body else {}


status, headers, body = rpc("initialize", {"protocolVersion": "2025-06-18", "capabilities": {}, "clientInfo": {"name": "ci-smoke", "version": "1"}})
check(status == 200 and "result" in body, "MCP initialize")
session, version = headers.get("mcp-session-id"), body["result"]["protocolVersion"]

status, _, body = rpc("tools/list", {}, session, version)
tools = [tool["name"] for tool in body.get("result", {}).get("tools", [])]
check(status == 200 and "pages_list" in tools, f"tools/list ({len(tools)} tools)")

status, _, body = rpc("tools/call", {"name": "pages_list", "arguments": {}}, session, version)
result = body.get("result", {})
pages = result.get("structuredContent", {}).get("pages", [])
check(status == 200 and not result.get("isError") and pages, f"pages_list reads the starter site ({len(pages)} pages)")
