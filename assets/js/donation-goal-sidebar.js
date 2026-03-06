(function (wp) {
  const { registerPlugin } = wp.plugins;
  const { PluginDocumentSettingPanel } = wp.editPost;
  const { ToggleControl, TextControl } = wp.components;
  const { useSelect, useDispatch } = wp.data;
  const { createElement } = wp.element;

  function DonationGoalPanel() {
    const meta = useSelect((select) => select('core/editor').getEditedPostAttribute('meta') || {}, []);
    const { editPost } = useDispatch('core/editor');

    const enabled = !!meta.dw_donation_goal_enabled;
    const goal = meta.dw_donation_goal || '';
    const raised = meta.dw_donation_raised || '';

    return createElement(
      PluginDocumentSettingPanel,
      { title: 'Donation Goal', className: 'donation-goal-panel' },
      createElement(ToggleControl, {
        label: 'Enable goal for this donation',
        checked: !!enabled,
        onChange: (v) => editPost({ meta: Object.assign({}, meta, { dw_donation_goal_enabled: v ? 1 : 0 }) }),
      }),
      createElement(TextControl, {
        label: 'Goal Amount',
        value: goal,
        onChange: (v) => editPost({ meta: Object.assign({}, meta, { dw_donation_goal: v }) }),
        type: 'number',
      }),
      createElement(TextControl, {
        label: 'Raised So Far (manual)',
        value: raised,
        onChange: (v) => editPost({ meta: Object.assign({}, meta, { dw_donation_raised: v }) }),
        type: 'number',
      })
    );
  }

  registerPlugin('dw-donation-goal-panel', { render: DonationGoalPanel });
})(window.wp);
