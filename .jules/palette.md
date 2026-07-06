## 2024-05-24 - Icon-only buttons lacking aria-labels
**Learning:** Found multiple icon-only buttons in `index.php` without `aria-label` attributes, affecting screen reader accessibility.
**Action:** Add `aria-label` attributes to these icon-only buttons for better accessibility.
## 2024-05-24 - Accessibility improvements for Icon-Only Buttons
**Learning:** Icon-only buttons often lack descriptive text for screen readers. In this application, several buttons used Bootstrap icons without accompanying text or labels.
**Action:** When adding or reviewing icon-only buttons, always ensure an `aria-label` is present to improve accessibility for visually impaired users.
## 2024-05-28 - Missing ARIA Labels on Icon-only Buttons
**Learning:** Found an app-wide pattern where icon-only action buttons (like Delete, Edit, Download) rely exclusively on the `title` attribute. While `title` gives a tooltip, it doesn't consistently announce to screen readers.
**Action:** Always verify icon-only buttons have explicit `aria-label` attributes describing their action, and set `aria-hidden="true"` on the interior icon elements to prevent redundant announcements.
## 2024-05-30 - App-wide Accessibility and Code Health Cleanup
**Learning:** Continued the effort to improve accessibility by ensuring all interior icons in buttons have `aria-hidden="true"` and all icon-only buttons have descriptive `aria-label` attributes. Also identified that production code contained numerous `console.log`, `console.warn`, and `console.error` statements which should be removed for better code health.
**Action:** Systematically updated `index.php`, `students.php`, `teachers.php`, `lecture.php`, `teacher_dashboard.php`, `device.php`, `includes/footer.php`, role-specific headers, `js/main.js`, and `js/face_recognition.js`. Added necessary ARIA attributes and removed all debugging console statements.
