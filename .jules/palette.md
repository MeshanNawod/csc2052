## 2024-05-24 - Icon-only buttons lacking aria-labels
**Learning:** Found multiple icon-only buttons in `index.php` without `aria-label` attributes, affecting screen reader accessibility.
**Action:** Add `aria-label` attributes to these icon-only buttons for better accessibility.
## 2024-05-24 - Accessibility improvements for Icon-Only Buttons
**Learning:** Icon-only buttons often lack descriptive text for screen readers. In this application, several buttons used Bootstrap icons without accompanying text or labels.
**Action:** When adding or reviewing icon-only buttons, always ensure an `aria-label` is present to improve accessibility for visually impaired users.
## 2024-05-28 - Missing ARIA Labels on Icon-only Buttons
**Learning:** Found an app-wide pattern where icon-only action buttons (like Delete, Edit, Download) rely exclusively on the `title` attribute. While `title` gives a tooltip, it doesn't consistently announce to screen readers.
**Action:** Always verify icon-only buttons have explicit `aria-label` attributes describing their action, and set `aria-hidden="true"` on the interior icon elements to prevent redundant announcements.

## 2026-07-04 - App-wide Accessibility Enhancements for Screen Readers
**Learning:** Found a recurring pattern of icon-only buttons (Scan, Manage, Delete, Download) that lacked descriptive labels for screen readers. While 'title' attributes provided visual tooltips, they didn't ensure robust accessibility.
**Action:** Implemented a comprehensive update across all major navigation headers, footers, and dashboards to add 'aria-label' attributes to interactive elements and 'aria-hidden="true"' to decorative icons, ensuring the application is usable by visually impaired users.
