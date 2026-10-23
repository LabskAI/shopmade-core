---
name: no-claims
description: Claims the demo front never makes — certifications, ratings, guarantees, prices, and cities or services outside facts.yaml.
---

# No claims

The front states only what facts.yaml supports. `php artisan front:lint demo` enforces the never_write list in facts.yaml; this skill explains the intent so you do not write around it.

- No certification, licence or insurance claims ("certified", "OSHA", "licensed and insured"). The owner has not given us proof.
- No ratings, rankings or awards ("5-star", "#1", "best in …", "award-winning"). The front does not publish reviews.
- No guarantees ("guaranteed"). Commitments are made by the office, not the website.
- No prices or currency amounts. Quotes come from the office and need an approval.
- Only the cities in facts.cities and the services in facts.services. A new area or service goes into facts.yaml first, with the owner's approval.
- When in doubt, leave the claim out and ask in the build thread.
