## 2024-05-24 - Icon-only buttons lacking aria-labels
**Learning:** Found multiple icon-only buttons in `index.php` without `aria-label` attributes, affecting screen reader accessibility.
**Action:** Add `aria-label` attributes to these icon-only buttons for better accessibility.
## 2024-05-24 - Accessibility improvements for Icon-Only Buttons
**Learning:** Icon-only buttons often lack descriptive text for screen readers. In this application, several buttons used Bootstrap icons without accompanying text or labels.
**Action:** When adding or reviewing icon-only buttons, always ensure an `aria-label` is present to improve accessibility for visually impaired users.
## 2026-05-26 - Added missing aria-labels to scattered icon-only buttons
**Learning:** Found scattered icon-only buttons throughout the application missing `aria-label` attributes and title text, especially within dynamically generated JavaScript sections and smaller components like the footer.
**Action:** Consistently apply `title` and `aria-label` to any new or modified icon-only button, regardless of whether it's generated dynamically or found in static HTML.
