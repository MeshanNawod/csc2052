## 2024-05-24 - Icon-only buttons lacking aria-labels
**Learning:** Found multiple icon-only buttons in `index.php` without `aria-label` attributes, affecting screen reader accessibility.
**Action:** Add `aria-label` attributes to these icon-only buttons for better accessibility.
## 2024-05-11 - Icon-only buttons accessibility pattern
**Learning:** Found multiple icon-only buttons across the application (e.g., in `teachers.php`, `students.php`) lacking `aria-label`s. Some have `title`s, but screen readers require `aria-label` for clear context, especially when only Bootstrap icons (`bi bi-*`) are present inside the `<button>` tag.
**Action:** Always verify that buttons containing only icons have both `aria-label` for screen readers and `title` for visual tooltips to provide a complete accessible experience.
