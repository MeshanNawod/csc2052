## 2024-05-24 - Icon-only buttons lacking aria-labels
**Learning:** Found multiple icon-only buttons in `index.php` without `aria-label` attributes, affecting screen reader accessibility.
**Action:** Add `aria-label` attributes to these icon-only buttons for better accessibility.
## 2024-05-24 - Accessibility improvements for Icon-Only Buttons
**Learning:** Icon-only buttons often lack descriptive text for screen readers. In this application, several buttons used Bootstrap icons without accompanying text or labels.
**Action:** When adding or reviewing icon-only buttons, always ensure an `aria-label` is present to improve accessibility for visually impaired users.
## 2024-05-28 - Missing ARIA Labels on Icon-only Buttons
**Learning:** Found an app-wide pattern where icon-only action buttons (like Delete, Edit, Download) rely exclusively on the `title` attribute. While `title` gives a tooltip, it doesn't consistently announce to screen readers.
**Action:** Always verify icon-only buttons have explicit `aria-label` attributes describing their action, and set `aria-hidden="true"` on the interior icon elements to prevent redundant announcements.

## 2024-07-05 - Global UI Components Require Careful ARIA Implementation
**Learning:** When adding global interactive components like the AI assistant (which appears on every page via `includes/footer.php`), it's extremely easy to miss screen reader support for icon-only buttons (like toggle, close, and send). If these aren't explicitly labeled, the entire assistant becomes inaccessible to screen reader users across the entire application.
**Action:** Always ensure that global floating UI elements (like chat toggles or modals) have explicit `aria-label` attributes on their buttons and `aria-hidden="true"` on their interior icon elements, as these components have outsized impact due to their presence on every page.
