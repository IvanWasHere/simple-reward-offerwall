/**
 * SimpleRO ReactJS Boilerplate — demo admin app built with ONLY WordPress libraries.
 *
 * No Mantine, no third-party React UI kit. Everything here comes from the core
 * WordPress packages that ship with WordPress: the `element` package for React
 * itself, `components` for the UI, `i18n` for translations, and `hooks` for
 * action/filter-style extensibility.
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
  const [ name, setName ] = useState( '' );

  // @wordpress/hooks lets other plugins extend your UI strings via a filter.
  // Any plugin can call addFilter('wpkirk.greeting', ...) to override the default.
  const greeting = applyFilters(
    'wpkirk.greeting',
    __( 'Welcome to the ReactJS boilerplate.', 'simple-reward-offerwall' )
  ) as string;

  return (
    <Flex direction="column" gap={ 4 } style={ { maxWidth: 600 } }>
      <Notice status="info" isDismissible={ false }>
        { greeting }
      </Notice>

      <Card>
        <CardHeader>
          <h2 style={ { margin: 0 } }>{ __( 'Counter', 'simple-reward-offerwall' ) }</h2>
        </CardHeader>
        <CardBody>
          <Flex align="center" gap={ 3 }>
            <FlexItem>
              <p style={ { fontSize: 18, margin: 0 } }>
                {
                  // translators: %d is the current counter value.
                  sprintf( __( 'Current value: %d', 'simple-reward-offerwall' ), count )
                }
              </p>
            </FlexItem>
            <FlexItem>
              <Button variant="primary" onClick={ increment }>
                { __( 'Increment', 'simple-reward-offerwall' ) }
              </Button>
            </FlexItem>
            <FlexItem>
              <Button variant="secondary" onClick={ reset }>
                { __( 'Reset', 'simple-reward-offerwall' ) }
              </Button>
            </FlexItem>
          </Flex>
        </CardBody>
      </Card>

      <Card>
        <CardHeader>
          <h2 style={ { margin: 0 } }>{ __( 'Text input (controlled)', 'simple-reward-offerwall' ) }</h2>
        </CardHeader>
        <CardBody>
          <TextControl
            label={ __( 'Your name', 'simple-reward-offerwall' ) }
            value={ name }
            onChange={ ( value ) => setName( value ) }
            placeholder={ __( 'Type here…', 'simple-reward-offerwall' ) }
            __nextHasNoMarginBottom
            __next40pxDefaultSize
          />
          { name && (
            <p style={ { marginTop: 12 } }>
              {
                // translators: %s is the name entered by the user.
                sprintf( __( 'Hello, %s!', 'simple-reward-offerwall' ), name )
              }
            </p>
          ) }
        </CardBody>
      </Card>
    </Flex>
  );
};

const container = document.getElementById( 'react-app' );
if ( container ) {
  createRoot( container ).render( <App /> );
}
