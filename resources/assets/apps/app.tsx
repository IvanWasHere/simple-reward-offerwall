/**
 * WPKirk ReactJS Boilerplate — demo admin app built with ONLY WordPress libraries.
 *
 * No Mantine, no third-party React UI kit. Everything here comes from the core
 * @wordpress/* packages that WordPress already ships: @wordpress/element for
 * React itself, @wordpress/components for the UI, @wordpress/i18n for translations,
 * @wordpress/hooks for action/filter-style extensibility.
 */

import {
  Button,
  Card,
  CardBody,
  CardHeader,
  Flex,
  FlexItem,
  Notice,
  TextControl,
} from '@wordpress/components';
import { createRoot, useState } from '@wordpress/element';
import { applyFilters } from '@wordpress/hooks';
import { __, sprintf } from '@wordpress/i18n';

import { useCounter } from './use-counter';

const App = () => {
  const { count, increment, reset } = useCounter();
  const [name, setName] = useState('');

  // @wordpress/hooks lets other plugins extend your UI strings via a filter.
  // Any plugin can call addFilter('wpkirk.greeting', ...) to override the default.
  const greeting = applyFilters(
    'wpkirk.greeting',
    __('Welcome to the ReactJS boilerplate.', 'wp-kirk')
  ) as string;

  return (
    <Flex direction="column" gap={4} style={{ maxWidth: 600 }}>
      <Notice status="info" isDismissible={false}>
        {greeting}
      </Notice>

      <Card>
        <CardHeader>
          <h2 style={{ margin: 0 }}>{__('Counter', 'wp-kirk')}</h2>
        </CardHeader>
        <CardBody>
          <Flex align="center" gap={3}>
            <FlexItem>
              <p style={{ fontSize: 18, margin: 0 }}>
                {sprintf(__('Current value: %d', 'wp-kirk'), count)}
              </p>
            </FlexItem>
            <FlexItem>
              <Button variant="primary" onClick={increment}>
                {__('Increment', 'wp-kirk')}
              </Button>
            </FlexItem>
            <FlexItem>
              <Button variant="secondary" onClick={reset}>
                {__('Reset', 'wp-kirk')}
              </Button>
            </FlexItem>
          </Flex>
        </CardBody>
      </Card>

      <Card>
        <CardHeader>
          <h2 style={{ margin: 0 }}>{__('Text input (controlled)', 'wp-kirk')}</h2>
        </CardHeader>
        <CardBody>
          <TextControl
            label={__('Your name', 'wp-kirk')}
            value={name}
            onChange={(value) => setName(value)}
            placeholder={__('Type here…', 'wp-kirk')}
            __nextHasNoMarginBottom
            __next40pxDefaultSize
          />
          {name && (
            <p style={{ marginTop: 12 }}>
              {sprintf(__('Hello, %s!', 'wp-kirk'), name)}
            </p>
          )}
        </CardBody>
      </Card>
    </Flex>
  );
};

const container = document.getElementById('react-app');
if (container) {
  createRoot(container).render(<App />);
}
