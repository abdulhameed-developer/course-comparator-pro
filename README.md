# Course Comparator Pro

A WordPress plugin for comparing courses. It maps JetEngine taxonomy metadata into course comparisons and supports server-side PDF generation. Administrators configure the mapping through an AJAX-driven settings interface.

## Install on a development site

1. Place the repository in a directory named `course-comparator-pro` under `wp-content/plugins/`. Keep the PHP entry point and the `assets/` directory together.
2. Open **Plugins** in WordPress and activate **Course Comparator**.
3. Configure the taxonomy metadata mapping for your course data. Compare courses with different field values, then check the generated PDF against the on-screen comparison.

## Repository layout

- `course-comparator-pro.php` — plugin entry point and PHP implementation.
- `assets/` — supporting assets used by the plugin.
