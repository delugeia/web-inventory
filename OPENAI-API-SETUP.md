# OpenAI API Setup

These directions cover the setup needed outside Web Inventory before the app can analyze cached webpages with OpenAI.

## 1. Create Or Sign In To An OpenAI Platform Account

Go to [platform.openai.com](https://platform.openai.com/) and create or sign in to an OpenAI Platform account.

Use the Platform/API dashboard, not only the consumer ChatGPT app.

## 2. Set Up Billing

Open the billing area:

[OpenAI billing overview](https://platform.openai.com/settings/organization/billing/overview)

OpenAI API usage requires billing to be configured. OpenAI currently supports prepaid billing, where API credits can be purchased and applied to future API usage. Purchased credits expire after one year and are non-refundable.

Reference: [OpenAI prepaid billing help](https://help.openai.com/en/articles/8264644-what-is-prepaid-billing)

## 3. Create A Project

In the OpenAI dashboard, create a dedicated project for this application.

Suggested project name:

```text
Web Inventory
```

Using a dedicated project keeps Web Inventory usage, limits, and keys separate from other OpenAI work.

## 4. Create An API Key

In the OpenAI dashboard, create a new API key for the Web Inventory project.

Suggested key name:

```text
web-inventory-local
```

Copy the key immediately and store it securely. You may not be able to view the full key again later.

Reference: [OpenAI developer quickstart](https://developers.openai.com/api/docs/quickstart)

## 5. Optional Organization And Project IDs

If the OpenAI account has access to multiple organizations or projects, collect these IDs from the dashboard:

- Organization ID
- Project ID

OpenAI API requests can include organization and project headers when calls need to be pinned to a specific account context.

Reference: [OpenAI API authentication](https://developers.openai.com/api/reference/overview#authentication)

## 6. Populate `.env`

Add these values to the local `.env` file:

```env
OPENAI_API_KEY=sk-proj-your_key_here
OPENAI_MODEL=gpt-5.5
OPENAI_ORGANIZATION=
OPENAI_PROJECT=
OPENAI_TIMEOUT_SECONDS=30
OPENAI_MAX_INPUT_CHARS=50000
OPENAI_ANALYSIS_ENABLED=true
```

Use `OPENAI_ORGANIZATION` and `OPENAI_PROJECT` only if Web Inventory needs to force requests into a specific OpenAI organization or project. Otherwise, leave them blank.

## Security Rules

- Do not commit the real `.env` file.
- Do not paste the API key into code, tests, README examples, screenshots, or logs.
- Treat the key as a server-side secret only.
- Do not expose the API key in browser-side JavaScript.
- If a key is exposed, revoke it in the OpenAI dashboard and create a new one.

Reference: [OpenAI API authentication](https://developers.openai.com/api/reference/overview#authentication)

## Implementation Note

Web Inventory should send OpenAI the resolver metadata and cached page content already gathered by the application. It should not rely on OpenAI to fetch the live webpage directly.
