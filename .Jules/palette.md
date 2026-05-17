## 2026-05-07 - Added ARIA and Accessibility Attributes to Login Form
**Learning:** Verified standard HTML accessibility practices in PHP templates without altering logic.
**Action:** Apply id/for matching and aria-hidden to decorative elements routinely.
## 2026-05-17 - Icon-only buttons accessibility
**Learning:** Found multiple instances of icon-only buttons (like delete or assign course buttons) lacking accessible labels in the teacher management page, which hurts screen reader usability.
**Action:** Always add descriptive `aria-label` attributes to buttons that contain only icons (`<i class="bi..."></i>`) and standard `btn-close` buttons to ensure accessibility.
