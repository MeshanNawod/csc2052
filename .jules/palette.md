## 2024-05-24 - Icon-only buttons lacking aria-labels
**Learning:** Found multiple icon-only buttons in `index.php` without `aria-label` attributes, affecting screen reader accessibility.
**Action:** Add `aria-label` attributes to these icon-only buttons for better accessibility.
## 2024-05-24 - Accessibility improvements for Icon-Only Buttons
**Learning:** Icon-only buttons often lack descriptive text for screen readers. In this application, several buttons used Bootstrap icons without accompanying text or labels.
**Action:** When adding or reviewing icon-only buttons, always ensure an `aria-label` is present to improve accessibility for visually impaired users.
## 2024-05-28 - Missing ARIA Labels on Icon-only Buttons
**Learning:** Found an app-wide pattern where icon-only action buttons (like Delete, Edit, Download) rely exclusively on the `title` attribute. While `title` gives a tooltip, it doesn't consistently announce to screen readers.
**Action:** Always verify icon-only buttons have explicit `aria-label` attributes describing their action, and set `aria-hidden="true"` on the interior icon elements to prevent redundant announcements.

## 2024-06-15 - Dynamically Generated Icon-Only Buttons Missing ARIA Labels
**Learning:** Found that while some static icon-only buttons might have ARIA labels, those generated dynamically in JavaScript (like the download CSV or email action buttons in `js/main.js`) often rely solely on `title` attributes. Screen readers may misinterpret these if not explicitly labeled with `aria-label`, and the inner `<i>` tags need `aria-hidden="true"` to prevent redundant/confusing announcements.
**Action:** When auditing for accessibility, specifically grep/search inside JavaScript template literals that render HTML elements to ensure accessibility standards are consistently applied to dynamically generated UI components.
