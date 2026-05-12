## 2024-05-24 - Icon-only buttons lacking aria-labels
**Learning:** Found multiple icon-only buttons in `index.php` without `aria-label` attributes, affecting screen reader accessibility.
**Action:** Add `aria-label` attributes to these icon-only buttons for better accessibility.
## 2024-05-24 - Icon-only buttons lacking ARIA labels
**Learning:** This app extensively uses Bootstrap Icons inside icon-only `<button>`s without accompanying screen reader text (e.g., `<button><i class="bi bi-trash"></i></button>`). `title` attributes were sometimes present but not consistently.
**Action:** Always verify that newly added or existing icon-only buttons include an `aria-label` attribute describing their action (e.g., `aria-label="Delete"`).
