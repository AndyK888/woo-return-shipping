## 2024-05-26 - Add ARIA Labels to Flex-Separated Inputs

**Learning:** When using flexbox (`justify-content: space-between`) to separate a label (often containing a checkbox) from its associated input (e.g., a fee amount), the visual grouping does not inherently translate to screen readers. If the input doesn't have an explicit `<label for="...">` or is wrapped in a way that breaks standard association, it will be read as an anonymous text field.
**Action:** Always provide an explicit `aria-label` directly on the input element (e.g., `aria-label="Return Shipping amount"`) when it is structurally detached from its descriptive text but visually related via layout tricks.
