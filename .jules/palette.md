## 2024-05-24 - Icon-only buttons lacking aria-labels
**Learning:** Found multiple icon-only buttons in `index.php` without `aria-label` attributes, affecting screen reader accessibility.
**Action:** Add `aria-label` attributes to these icon-only buttons for better accessibility.
## 2024-05-24 - Accessibility improvements for Icon-Only Buttons
**Learning:** Icon-only buttons often lack descriptive text for screen readers. In this application, several buttons used Bootstrap icons without accompanying text or labels.
**Action:** When adding or reviewing icon-only buttons, always ensure an `aria-label` is present to improve accessibility for visually impaired users.
## 2024-05-24 - Accessibility for Icon-only Buttons
**Learning:** Found multiple icon-only buttons (using Bootstrap Icons like `<i class="bi bi-key"></i>` inside `<button>`) lacking `aria-label` attributes across different PHP templates (e.g. `teachers.php`).
**Action:** Always ensure that any button containing only an icon is supplemented with a descriptive `aria-label` attribute to ensure screen reader compatibility, maintaining the standard that good UX is accessible to everyone.
