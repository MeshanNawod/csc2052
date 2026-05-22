## 2024-05-24 - Icon-only buttons lacking aria-labels
**Learning:** Found multiple icon-only buttons in `index.php` without `aria-label` attributes, affecting screen reader accessibility.
**Action:** Add `aria-label` attributes to these icon-only buttons for better accessibility.
## 2024-05-24 - Accessibility improvements for Icon-Only Buttons
**Learning:** Icon-only buttons often lack descriptive text for screen readers. In this application, several buttons used Bootstrap icons without accompanying text or labels.
**Action:** When adding or reviewing icon-only buttons, always ensure an `aria-label` is present to improve accessibility for visually impaired users.
## 2026-05-22 - Adding aria-label to Icon-Only Buttons
**Learning:** Found an accessibility pattern specific to this app where multiple icon-only buttons lacked `aria-label`s, which is an accessibility anti-pattern. Learned not to commit temporary scripts when programmatically making edits, and to ensure aria-labels are visually distinct from existing visible button text to avoid screen-reader duplication issues.
**Action:** Always clean up throwaway Python or Bash scripts used during fixes to keep the repository clean.
