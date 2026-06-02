## 2026-05-07 - Added ARIA and Accessibility Attributes to Login Form
**Learning:** Verified standard HTML accessibility practices in PHP templates without altering logic.
**Action:** Apply id/for matching and aria-hidden to decorative elements routinely.
## 2026-06-02 - Icon-only Search Buttons in Enrollment
**Learning:** Found multiple icon-only "Search" buttons in the admin enrollment section (`students.php`) relying solely on `title` attributes ("Auto Find Latest Scan") without `aria-label` or `aria-hidden` on the inner icons. While `title` gives hover context, screen readers benefit greatly from explicit `aria-label`.
**Action:** Always ensure any `<button>` containing only an `<i>` tag has a descriptive `aria-label`, and the inner `<i>` has `aria-hidden="true"`.
