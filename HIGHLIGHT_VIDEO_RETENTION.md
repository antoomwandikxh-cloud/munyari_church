# Church highlight retention

All church highlight photos and videos are removed from both `uploads/` and `church_highlights` one year after upload. Existing highlights use their original upload date. Dashboard visits also run cleanup, and the CLI job can be scheduled daily so cleanup does not depend on someone visiting the site.

On the hosted server, add a daily cron job (for example, 3:00 AM) that runs:

```sh
php /absolute/path/to/munyari_church/cleanup_church_highlights.php
```

Use the PHP CLI path and project path provided by the hosting provider. The cleanup script only runs through CLI, not as a public web page.
