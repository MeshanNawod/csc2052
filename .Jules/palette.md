## 2026-05-07 - Added ARIA and Accessibility Attributes to Login Form
**Learning:** Verified standard HTML accessibility practices in PHP templates without altering logic.
**Action:** Apply id/for matching and aria-hidden to decorative elements routinely.
## 2026-06-21 - Added Accessibility Attributes to Dynamically Generated Icon Buttons
**Learning:** Icon-only buttons generated via client-side JavaScript (e.g., in `students.php`) often rely only on `title` attributes for tooltips but miss `aria-label` for proper screen reader announcement. Inner icons (`<i class="bi ..."></i>` or textual icons like `&times;`) lack `aria-hidden="true"`.
**Action:** Consistently ensure that when mapping UI elements or generating them via string concatenation in JS, icon-only interactive elements receive corresponding `aria-label` attributes and internal symbols are hidden from assistive technology using `aria-hidden="true"`.
