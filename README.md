# Astuteo Pulse
Only useful for Astuteo clients. Decoupled from our Toolkit. Connecting Astuteo client sites to our monitor. Heavily "inspired" by Viget's module.

Right now we're simply mapping the info to Airtable.

### Setup
Add to server's .env file to send report:
```
AIRTABLE_API_KEY=""
AIRTABLE_BASE=""
```

This will send a report of the system to Airtable once a day. This is triggered by accessing the admin. To automate this, set up a cron job and point to:

`<path/to/craft/install>/craft astuteo-pulse/default/take-pulse`