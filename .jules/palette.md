## 2024-05-24 - Icon-only buttons lacking aria-labels
**Learning:** Found multiple icon-only buttons in `index.php` without `aria-label` attributes, affecting screen reader accessibility.
**Action:** Add `aria-label` attributes to these icon-only buttons for better accessibility.
## 2024-05-24 - Accessibility improvements for Icon-Only Buttons
**Learning:** Icon-only buttons often lack descriptive text for screen readers. In this application, several buttons used Bootstrap icons without accompanying text or labels.
**Action:** When adding or reviewing icon-only buttons, always ensure an `aria-label` is present to improve accessibility for visually impaired users.
## 2024-05-23 - Accessibility Patterns in Sentinel Swarm AMS v3\n**Learning:** Icon-only buttons relying on Bootstrap Icons (`bi bi-*`) are pervasive across the UI, particularly in dynamic JavaScript template strings building table rows and actions. These dynamically generated elements are a critical, often-missed area for `aria-label` injection to ensure screen readers can announce dynamic UI changes.\n**Action:** When adding accessibility attributes to a PHP/JS hybrid application, proactively audit both the static HTML structure and any inline JS functions that construct DOM elements dynamically.
