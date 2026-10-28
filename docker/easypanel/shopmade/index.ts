import {
  Output,
  randomPassword,
  randomString,
  Services,
} from "~templates-utils";
import { Input } from "./meta";

export function generate(input: Input): Output {
  const services: Services = [];
  const databasePassword = randomPassword();
  const appKey = randomString(32);

  services.push({
    type: "app",
    data: {
      serviceName: input.appServiceName,
      env: [
        `APP_KEY=${appKey}`,
        `APP_URL=https://$(PRIMARY_DOMAIN)`,
        `APP_ORG_NAME=${input.orgName}`,
        `AGENTS_OAUTH_ALLOW_LOOPBACK=true`,
        `DB_CONNECTION=pgsql`,
        `DB_HOST=$(PROJECT_NAME)_${input.databaseServiceName}`,
        `DB_PORT=5432`,
        `DB_DATABASE=$(PROJECT_NAME)`,
        `DB_USERNAME=postgres`,
        `DB_PASSWORD=${databasePassword}`,
      ].join("\n"),
      source: {
        type: "image",
        image: input.appServiceImage,
      },
      domains: [
        {
          host: "$(EASYPANEL_DOMAIN)",
          port: 8080,
        },
      ],
      mounts: [
        {
          type: "volume",
          name: "storage",
          mountPath: "/var/www/shopmade/storage",
        },
        {
          type: "volume",
          name: "content",
          mountPath: "/var/www/shopmade/content",
        },
      ],
    },
  });

  services.push({
    type: "postgres",
    data: {
      serviceName: input.databaseServiceName,
      password: databasePassword,
    },
  });

  return { services };
}
