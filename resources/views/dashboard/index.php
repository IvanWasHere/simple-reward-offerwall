<!--
 |
 | In $plugin you'll find an instance of Plugin class.
 | If you'd like can pass variable to this view, for example:
 |
 | return PluginClassName()->view( 'dashboard.index', [ 'var' => 'value' ] );
 |
-->

<?php ob_start() ?>

<div class="simple-reward-offerwall wrap simple-reward-offerwall-sample">

  <div class="simple-reward-offerwall-toc-content">

    <?php wpkirk_section(__('Live Demo', 'simple-reward-offerwall')); ?>
    <p>
      <?= esc_html__(
        'React built with only the @wordpress/* runtime libraries — no Mantine, no third-party UI kit. The components below are @wordpress/components (Button, Card, Notice, TextControl, Flex), state uses @wordpress/element, i18n uses @wordpress/i18n, and the greeting is filterable via @wordpress/hooks applyFilters("wpkirk.greeting", …).',
        'simple-reward-offerwall'
      ) ?>
    </p>

    <div id="react-app"></div>

    <?php wpkirk_section(__('React Entry', 'simple-reward-offerwall')); ?>
    <?php wpkirk_code('@/resources/assets/apps/app.tsx'); ?>

    <?php wpkirk_section(__('Custom Hook', 'simple-reward-offerwall')); ?>
    <?php wpkirk_code('@/resources/assets/apps/use-counter.ts'); ?>

    <?php wpkirk_section(__('Jest Test', 'simple-reward-offerwall')); ?>
    <?php wpkirk_code('@/resources/assets/apps/__tests__/use-counter.test.tsx'); ?>

    <?php wpkirk_section(__('Controller', 'simple-reward-offerwall')); ?>
    <?php wpkirk_code('@/plugin/Http/Controllers/Dashboard/DashboardController.php'); ?>

    <?php wpkirk_section(__('Package.json', 'simple-reward-offerwall')); ?>
    <?php wpkirk_code('@/package.json'); ?>

    <?php wpkirk_section(__('Developing', 'simple-reward-offerwall')); ?>
    <?php wpkirk_code('yarn dev', ['language' => 'sh']); ?>

    <?php wpkirk_section(__('Build', 'simple-reward-offerwall')); ?>
    <?php wpkirk_code('yarn build', ['language' => 'sh']); ?>

    <?php wpkirk_section(__('Test', 'simple-reward-offerwall')); ?>
    <?php wpkirk_code('yarn test', ['language' => 'sh']); ?>

  </div>

  <?php wpkirk_toc('ReactJS') ?>

</div>
