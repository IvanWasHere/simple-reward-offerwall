<!--
 |
 | In $plugin you'll find an instance of Plugin class.
 | If you'd like can pass variable to this view, for example:
 |
 | return PluginClassName()->view( 'dashboard.index', [ 'var' => 'value' ] );
 |
-->

<?php ob_start() ?>

<div class="wp-kirk wrap wp-kirk-sample">

  <div class="wp-kirk-toc-content">

    <?php wpkirk_section(__('Live Demo', 'wp-kirk')); ?>
    <p>
      <?= esc_html__(
        'React built with only the @wordpress/* runtime libraries — no Mantine, no third-party UI kit. The components below are @wordpress/components (Button, Card, Notice, TextControl, Flex), state uses @wordpress/element, i18n uses @wordpress/i18n, and the greeting is filterable via @wordpress/hooks applyFilters("wpkirk.greeting", …).',
        'wp-kirk'
      ) ?>
    </p>

    <div id="react-app"></div>

    <?php wpkirk_section(__('React Entry', 'wp-kirk')); ?>
    <?php wpkirk_code('@/resources/assets/apps/app.tsx'); ?>

    <?php wpkirk_section(__('Custom Hook', 'wp-kirk')); ?>
    <?php wpkirk_code('@/resources/assets/apps/use-counter.ts'); ?>

    <?php wpkirk_section(__('Jest Test', 'wp-kirk')); ?>
    <?php wpkirk_code('@/resources/assets/apps/__tests__/use-counter.test.tsx'); ?>

    <?php wpkirk_section(__('Controller', 'wp-kirk')); ?>
    <?php wpkirk_code('@/plugin/Http/Controllers/Dashboard/DashboardController.php'); ?>

    <?php wpkirk_section(__('Package.json', 'wp-kirk')); ?>
    <?php wpkirk_code('@/package.json'); ?>

    <?php wpkirk_section(__('Developing', 'wp-kirk')); ?>
    <?php wpkirk_code('yarn dev', ['language' => 'sh']); ?>

    <?php wpkirk_section(__('Build', 'wp-kirk')); ?>
    <?php wpkirk_code('yarn build', ['language' => 'sh']); ?>

    <?php wpkirk_section(__('Test', 'wp-kirk')); ?>
    <?php wpkirk_code('yarn test', ['language' => 'sh']); ?>

  </div>

  <?php wpkirk_toc('ReactJS') ?>

</div>
