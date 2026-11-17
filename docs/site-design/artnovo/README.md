# ArtNovo native design reference

These references record the published public design read through Elementor's
supported MCP API. They are separate from the Core runtime ZIP. They are not
Elementor import files, a database dump, a migration or a complete site backup.
Never write them into `_elementor_data` or treat them as an automatic restore.

`manifest.json` records site identity, document types/templates, public page
assignments, active header/footer conditions and SHA-256 of the public references:

- `home.json`: document 68, root `3ffe5619`, 63 nodes.
- `header.json`: document 75, root `422c4365`, 5 nodes.
- `footer.json`: document 86, root `42e566cc`, 13 nodes.
- `terms.json`, `privacy.json`, `refunds.json`: published legal pages 132/134/136.
- `product.json`: native product template 35, root `fcd870e`; its saved conditions
  remain product categories 18/19. The site-part tool's context-dependent active flag
  is not proof that it is unused; public product HTML renders document 35.

[The 1.4.0 native operation contract](planned-1.4.0.json) records the proposed
header desktop alignment, product-template presentation and main landmarks, including supported inverse
settings/styles. The initial operations are now published after the official Core install. The
manifest records observed source; the contract separately marks any proposed
post-publication refinement until its supported readback is complete. The placeholder
wrapper correction and currency-spacing setting are published and verified.
[The 1.4.1 contract](planned-1.4.1.json) proposes left-aligned product-detail prices
with currency on their left; it was applied after official1.4.1 install and verified
by supported source comparison and desktop/mobile geometry.

Snapshots preserve native IDs, hierarchy, content/dynamic bindings, local styles
and interactions from the supported read. Generated default styles and tool editor
URLs are omitted. No credentials, nonces, cookies, customer/order data or private
WordPress options are included. Media attachment IDs/URLs are references; image
binaries and the whole Elementor kit are not bundled. The manifest's template
values are actual WordPress API values, including an empty default template.
WordPress post modification dates are not used as design revision evidence because
the native metadata edits do not update them reliably.

[The 1.7.0 native configuration contract](planned-1.7.0.json) records the approved
BACS description-only update for issue24. Apply after the official Core install;
retain instructions, account details and every other gateway setting.

## Change sequence

For future changes, capture the relevant supported native source and prepare the
scoped operation contract in the GitHub change first. Review the target, intended
styles/content, stable IDs/queries/links, recovery boundary and checks before live
application. For a mission delivered with a Core release, publish the approved
plugin artifact before applying its associated live design/configuration changes.
Apply only through supported Elementor/WordPress APIs; then read back, compare and
verify the published page. Commit the resulting reference and delivery evidence.
Never infer editing authority from a snapshot or import it as storage JSON.

The existing layout changes were captured after their live publication. Recording
them now closes the provenance gap; it does not rewrite their historical order.
The chronology and exact Core artifact identity are in the
[delivery evidence](../../evidence/2026-11-16-ui-refinements.md).
