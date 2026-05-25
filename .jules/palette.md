## 2024-05-24 - Icon-only buttons lacking aria-labels
**Learning:** Found multiple icon-only buttons in `index.php` without `aria-label` attributes, affecting screen reader accessibility.
**Action:** Add `aria-label` attributes to these icon-only buttons for better accessibility.
## 2024-05-24 - Accessibility improvements for Icon-Only Buttons
**Learning:** Icon-only buttons often lack descriptive text for screen readers. In this application, several buttons used Bootstrap icons without accompanying text or labels.
**Action:** When adding or reviewing icon-only buttons, always ensure an `aria-label` is present to improve accessibility for visually impaired users.
## 2024-06-25 - Dynamic JS icon-only buttons lacking aria-labels
**Learning:** Found multiple icon-only buttons being generated via string interpolation in `js/main.js` without `aria-label` attributes, affecting screen reader accessibility.
**Action:** When adding or modifying JavaScript that generates HTML, ensure `aria-label` attributes are included on any icon-only elements.
