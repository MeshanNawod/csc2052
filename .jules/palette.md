## 2024-05-24 - Icon-only buttons lacking aria-labels
**Learning:** Found multiple icon-only buttons in `index.php` without `aria-label` attributes, affecting screen reader accessibility.
**Action:** Add `aria-label` attributes to these icon-only buttons for better accessibility.
## 2024-05-24 - Accessibility improvements for Icon-Only Buttons
**Learning:** Icon-only buttons often lack descriptive text for screen readers. In this application, several buttons used Bootstrap icons without accompanying text or labels.
**Action:** When adding or reviewing icon-only buttons, always ensure an `aria-label` is present to improve accessibility for visually impaired users.
## 2024-05-28 - Missing ARIA Labels on Icon-only Buttons
**Learning:** Found an app-wide pattern where icon-only action buttons (like Delete, Edit, Download) rely exclusively on the `title` attribute. While `title` gives a tooltip, it doesn't consistently announce to screen readers.
**Action:** Always verify icon-only buttons have explicit `aria-label` attributes describing their action, and set `aria-hidden="true"` on the interior icon elements to prevent redundant announcements.
## 2026-06-19 - Adding ARIA labels to dynamically generated HTML elements
**Learning:** In applications where HTML UI components are dynamically generated using JavaScript template literals, it's crucial to ensure that accessibility attributes like `aria-label` and `aria-hidden` are consistently applied within the template strings, as these elements are not present in the initial static HTML markup.
**Action:** Always inspect JavaScript files for dynamic HTML generation (e.g., using template literals or DOM manipulation) when performing accessibility audits, and inject necessary ARIA attributes directly into the generated markup strings.
