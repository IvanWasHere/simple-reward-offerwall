/**
 * Simple Reward Offerwall — user SPA (Phase 1: authentication).
 *
 * Built with only the @wordpress/* runtime libraries. Hash-routed so it can live
 * on any WordPress page via the [simple_ro_user_app] shortcode. All state-changing
 * calls carry the CSRF header; the session rides in an httpOnly cookie.
 *
 * Later phases add the offerwall, rewards, payouts and support views behind auth.
 */
import {
  Button,
  Card,
  CardBody,
  CardHeader,
  Flex,
  Notice,
  Spinner,
  TextControl,
} from '@wordpress/components';
import { createRoot, useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

import { api, ApiError } from './api';

interface User {
  id: number;
  email: string;
  type: string;
  status: string;
  displayName: string;
  hash: string;
}

type Route =
  | { name: 'login' }
  | { name: 'register' }
  | { name: 'forgot' }
  | { name: 'reset'; token: string };

function parseRoute(): Route {
  const hash = window.location.hash.replace( /^#\/?/, '' );
  const [ path, query ] = hash.split( '?' );
  const params = new URLSearchParams( query || '' );

  switch ( path ) {
    case 'register':
      return { name: 'register' };
    case 'forgot':
      return { name: 'forgot' };
    case 'reset':
      return { name: 'reset', token: params.get( 'token' ) || '' };
    default:
      return { name: 'login' };
  }
}

function navigate( hash: string ): void {
  window.location.hash = hash;
}

/* ------------------------------------------------------------------ */

const App = () => {
  const [ loading, setLoading ] = useState( true );
  const [ user, setUser ] = useState< User | null >( null );
  const [ route, setRoute ] = useState< Route >( parseRoute() );

  useEffect( () => {
    const onHash = () => setRoute( parseRoute() );
    window.addEventListener( 'hashchange', onHash );
    return () => window.removeEventListener( 'hashchange', onHash );
  }, [] );

  useEffect( () => {
    api< { user: User } >( '/auth/me' )
      .then( ( res ) => setUser( res.user ) )
      .catch( () => setUser( null ) )
      .finally( () => setLoading( false ) );
  }, [] );

  if ( loading ) {
    return (
      <Flex justify="center" style={ { padding: 48 } }>
        <Spinner />
      </Flex>
    );
  }

  if ( user ) {
    return <Dashboard user={ user } onLogout={ () => setUser( null ) } />;
  }

  const onAuthenticated = ( u: User ) => setUser( u );

  switch ( route.name ) {
    case 'register':
      return <Register onDone={ onAuthenticated } />;
    case 'forgot':
      return <Forgot />;
    case 'reset':
      return <Reset token={ route.token } />;
    default:
      return <Login onDone={ onAuthenticated } />;
  }
};

/* ------------------------------------------------------------------ */

const Shell = ( { title, children }: { title: string; children: React.ReactNode } ) => (
  <div style={ { maxWidth: 440, margin: '32px auto' } }>
    <Card>
      <CardHeader>
        <h2 style={ { margin: 0 } }>{ title }</h2>
      </CardHeader>
      <CardBody>{ children }</CardBody>
    </Card>
  </div>
);

const ErrorNotice = ( { message }: { message: string | null } ) =>
  message ? (
    <Notice status="error" isDismissible={ false }>
      { message }
    </Notice>
  ) : null;

/* ------------------------------------------------------------------ */

const Login = ( { onDone }: { onDone: ( u: User ) => void } ) => {
  const [ email, setEmail ] = useState( '' );
  const [ password, setPassword ] = useState( '' );
  const [ error, setError ] = useState< string | null >( null );
  const [ busy, setBusy ] = useState( false );

  const submit = async () => {
    setBusy( true );
    setError( null );
    try {
      const res = await api< { user: User } >( '/auth/login', {
        method: 'POST',
        body: { email, password },
      } );
      onDone( res.user );
    } catch ( e ) {
      setError(
        e instanceof ApiError ? e.message : __( 'Something went wrong.', 'simple-reward-offerwall' )
      );
    } finally {
      setBusy( false );
    }
  };

  return (
    <Shell title={ __( 'Sign in', 'simple-reward-offerwall' ) }>
      <ErrorNotice message={ error } />
      <TextControl
        label={ __( 'Email', 'simple-reward-offerwall' ) }
        type="email"
        value={ email }
        onChange={ setEmail }
        __nextHasNoMarginBottom
        __next40pxDefaultSize
      />
      <TextControl
        label={ __( 'Password', 'simple-reward-offerwall' ) }
        type="password"
        value={ password }
        onChange={ setPassword }
        __nextHasNoMarginBottom
        __next40pxDefaultSize
      />
      <div style={ { marginTop: 16 } }>
        <Button variant="primary" onClick={ submit } isBusy={ busy } disabled={ busy }>
          { __( 'Sign in', 'simple-reward-offerwall' ) }
        </Button>
      </div>
      <p style={ { marginTop: 16 } }>
        <a href="#/register">{ __( 'Create an account', 'simple-reward-offerwall' ) }</a>
        { ' · ' }
        <a href="#/forgot">{ __( 'Forgot password?', 'simple-reward-offerwall' ) }</a>
      </p>
    </Shell>
  );
};

const Register = ( { onDone }: { onDone: ( u: User ) => void } ) => {
  const [ displayName, setDisplayName ] = useState( '' );
  const [ email, setEmail ] = useState( '' );
  const [ password, setPassword ] = useState( '' );
  const [ error, setError ] = useState< string | null >( null );
  const [ busy, setBusy ] = useState( false );

  const submit = async () => {
    setBusy( true );
    setError( null );
    try {
      const res = await api< { user: User } >( '/auth/register', {
        method: 'POST',
        body: { display_name: displayName, email, password },
      } );
      onDone( res.user );
    } catch ( e ) {
      setError(
        e instanceof ApiError ? e.message : __( 'Something went wrong.', 'simple-reward-offerwall' )
      );
    } finally {
      setBusy( false );
    }
  };

  return (
    <Shell title={ __( 'Create your account', 'simple-reward-offerwall' ) }>
      <ErrorNotice message={ error } />
      <TextControl
        label={ __( 'Name', 'simple-reward-offerwall' ) }
        value={ displayName }
        onChange={ setDisplayName }
        __nextHasNoMarginBottom
        __next40pxDefaultSize
      />
      <TextControl
        label={ __( 'Email', 'simple-reward-offerwall' ) }
        type="email"
        value={ email }
        onChange={ setEmail }
        __nextHasNoMarginBottom
        __next40pxDefaultSize
      />
      <TextControl
        label={ __( 'Password', 'simple-reward-offerwall' ) }
        type="password"
        help={ __( 'At least 10 characters.', 'simple-reward-offerwall' ) }
        value={ password }
        onChange={ setPassword }
        __nextHasNoMarginBottom
        __next40pxDefaultSize
      />
      <div style={ { marginTop: 16 } }>
        <Button variant="primary" onClick={ submit } isBusy={ busy } disabled={ busy }>
          { __( 'Create account', 'simple-reward-offerwall' ) }
        </Button>
      </div>
      <p style={ { marginTop: 16 } }>
        <a href="#/login">
          { __( 'Already have an account? Sign in', 'simple-reward-offerwall' ) }
        </a>
      </p>
    </Shell>
  );
};

const Forgot = () => {
  const [ email, setEmail ] = useState( '' );
  const [ message, setMessage ] = useState< string | null >( null );
  const [ busy, setBusy ] = useState( false );

  const submit = async () => {
    setBusy( true );
    try {
      const res = await api< { message: string } >( '/auth/forgot', {
        method: 'POST',
        body: { email },
      } );
      setMessage( res.message );
    } catch {
      // forgot always returns 200; ignore
    } finally {
      setBusy( false );
    }
  };

  return (
    <Shell title={ __( 'Reset your password', 'simple-reward-offerwall' ) }>
      { message ? (
        <Notice status="success" isDismissible={ false }>
          { message }
        </Notice>
      ) : (
        <>
          <TextControl
            label={ __( 'Email', 'simple-reward-offerwall' ) }
            type="email"
            value={ email }
            onChange={ setEmail }
            __nextHasNoMarginBottom
            __next40pxDefaultSize
          />
          <div style={ { marginTop: 16 } }>
            <Button variant="primary" onClick={ submit } isBusy={ busy } disabled={ busy }>
              { __( 'Send reset link', 'simple-reward-offerwall' ) }
            </Button>
          </div>
        </>
      ) }
      <p style={ { marginTop: 16 } }>
        <a href="#/login">{ __( 'Back to sign in', 'simple-reward-offerwall' ) }</a>
      </p>
    </Shell>
  );
};

const Reset = ( { token }: { token: string } ) => {
  const [ password, setPassword ] = useState( '' );
  const [ error, setError ] = useState< string | null >( null );
  const [ done, setDone ] = useState< string | null >( null );
  const [ busy, setBusy ] = useState( false );

  const submit = async () => {
    setBusy( true );
    setError( null );
    try {
      const res = await api< { message: string } >( '/auth/reset', {
        method: 'POST',
        body: { token, password },
      } );
      setDone( res.message );
    } catch ( e ) {
      setError(
        e instanceof ApiError ? e.message : __( 'Something went wrong.', 'simple-reward-offerwall' )
      );
    } finally {
      setBusy( false );
    }
  };

  return (
    <Shell title={ __( 'Choose a new password', 'simple-reward-offerwall' ) }>
      { done ? (
        <>
          <Notice status="success" isDismissible={ false }>
            { done }
          </Notice>
          <p style={ { marginTop: 16 } }>
            <a href="#/login">{ __( 'Sign in', 'simple-reward-offerwall' ) }</a>
          </p>
        </>
      ) : (
        <>
          <ErrorNotice message={ error } />
          <TextControl
            label={ __( 'New password', 'simple-reward-offerwall' ) }
            type="password"
            help={ __( 'At least 10 characters.', 'simple-reward-offerwall' ) }
            value={ password }
            onChange={ setPassword }
            __nextHasNoMarginBottom
            __next40pxDefaultSize
          />
          <div style={ { marginTop: 16 } }>
            <Button
              variant="primary"
              onClick={ submit }
              isBusy={ busy }
              disabled={ busy || ! token }
            >
              { __( 'Reset password', 'simple-reward-offerwall' ) }
            </Button>
          </div>
        </>
      ) }
    </Shell>
  );
};

const Dashboard = ( { user, onLogout }: { user: User; onLogout: () => void } ) => {
  const [ busy, setBusy ] = useState( false );

  const logout = async () => {
    setBusy( true );
    try {
      await api( '/auth/session', { method: 'DELETE' } );
    } catch {
      // ignore — clear locally regardless
    } finally {
      setBusy( false );
      onLogout();
      navigate( '/login' );
    }
  };

  return (
    <div style={ { maxWidth: 720, margin: '32px auto' } }>
      <Card>
        <CardHeader>
          <Flex justify="space-between">
            <h2 style={ { margin: 0 } }>{ __( 'Your dashboard', 'simple-reward-offerwall' ) }</h2>
            <Button variant="secondary" onClick={ logout } isBusy={ busy } disabled={ busy }>
              { __( 'Sign out', 'simple-reward-offerwall' ) }
            </Button>
          </Flex>
        </CardHeader>
        <CardBody>
          <p>
            { __( 'Signed in as', 'simple-reward-offerwall' ) }{ ' ' }
            <strong>{ user.displayName || user.email }</strong>.
          </p>
          <p style={ { marginTop: 12, opacity: 0.7 } }>
            { __(
              'Offers, rewards and payouts arrive in the next phases.',
              'simple-reward-offerwall'
            ) }
          </p>
        </CardBody>
      </Card>
    </div>
  );
};

/* ------------------------------------------------------------------ */

const container = document.getElementById( 'simple-ro-user-root' );
if ( container ) {
  createRoot( container ).render( <App /> );
}
