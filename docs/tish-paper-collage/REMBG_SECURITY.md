# Security and integration notes

The background-removal worker only reads locally stored JPEG/PNG/WebP files inside the Laravel public or storage/app/public trees. It does not fetch arbitrary URLs, does not run shell expressions, and checks input size, image dimensions and MIME type before running Python. The Python executable is configured on the server; it does not come from user input.

A deterministic hash of the original image path names the derived PNG. The cached file is considered valid only while its modification time is at least as recent as the source. Existing customer-menu asset routes now allow the generated PNG directory.

Image processing uses the dedicated database queue after the ORM save event; do not use the synchronous queue for this workload. Only enable the feature after deploying the virtualenv and worker. Backfill command provides both queue and synchronous modes. Original images remain in the existing product/restaurant-menu tables.

No database schema migration, no upstream AI API, and no modification to pricing/payment code.
