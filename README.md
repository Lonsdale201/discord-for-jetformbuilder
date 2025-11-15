# Discord for JetFormBuilder

Stable tag: 1.0.0

### Installation
Install and activate the plugin like any other WordPress plugin (upload it to `wp-content/plugins`, then enable it from the Plugins screen).

### Configure Discord integration
1. Go to `JetFormBuilder → Settings` and open the **Discord** tab.
2. Fill out the available settings:
   - **Discord webhook URL** – paste the webhook for the Discord channel that should receive messages from the “Discord Notification” action inside your form.
   - **Submission notification webhook URL** – webhook for the channel that should receive global submission summaries. No additional action is required; the plugin will send them automatically.
   - **Only failed submissions** – when enabled, the submission notification webhook is triggered only if at least one action fails during processing.

### Creating a Discord webhook
1. Open your Discord server (you need permission to manage webhooks).
2. Select the target channel, click **Edit Channel → Integrations → Create Webhook**.
3. Optionally rename the webhook, change its avatar, or assign it to another channel.
4. Click **Copy Webhook URL** and paste it into the matching field in JetFormBuilder settings.

### Post Submit Actions
Inside JetFormBuilder form settings, add the **Discord Notification** action. It supports:
- **Discord message** – compose the payload; use JetFormBuilder macros to include form values. Discord formatting is supported and the message is rendered as an embed.
- **Include refer URL** – appends the page URL where the form was submitted.
- **Include form name** – appends the form title to the message footer.

With the configuration above, your forms will deliver messages to Discord channels automatically.
