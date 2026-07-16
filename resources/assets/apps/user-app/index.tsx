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
  TextareaControl,
} from '@wordpress/components';
import { createRoot, useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

import { api, ApiError } from '../shared/api';

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
  const [ balance, setBalance ] = useState< number | null >( null );
  const [ tick, setTick ] = useState( 0 );
  const bump = () => setTick( ( t ) => t + 1 );

  useEffect( () => {
    api< { balance: number } >( '/me/balance' )
      .then( ( r ) => setBalance( r.balance ) )
      .catch( () => setBalance( null ) );
  }, [ tick ] );

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
          <p style={ { fontSize: 20, marginTop: 8 } }>
            { __( 'Balance:', 'simple-reward-offerwall' ) }{ ' ' }
            <strong>{ balance === null ? '…' : balance }</strong>{ ' ' }
            { __( 'coins', 'simple-reward-offerwall' ) }
          </p>
        </CardBody>
      </Card>

      <div style={ { marginTop: 24 } }>
        <Offers />
      </div>

      <div style={ { marginTop: 24 } }>
        <Offerwalls />
      </div>

      <div style={ { marginTop: 24 } }>
        <Payouts onRedeemed={ bump } />
      </div>

      <div style={ { marginTop: 24 } }>
        <MyRedemptions tick={ tick } />
      </div>

      <div style={ { marginTop: 24 } }>
        <Rewards />
      </div>

      <div style={ { marginTop: 24 } }>
        <Support />
      </div>
    </div>
  );
};

/* ------------------------------------------------------------------ */

interface Offer {
  providerId: number;
  providerName: string;
  providerOfferId: string;
  name: string;
  tasks: unknown;
  totalPayout: number;
  device: string;
  os: string;
  country: string;
  icons: Record< string, string >;
  source: string;
}

const Offers = () => {
  const [ offers, setOffers ] = useState< Offer[] >( [] );
  const [ loading, setLoading ] = useState( true );

  useEffect( () => {
    api< { offers: Offer[] } >( '/offers' )
      .then( ( r ) => setOffers( r.offers || [] ) )
      .catch( () => setOffers( [] ) )
      .finally( () => setLoading( false ) );
  }, [] );

  const start = async ( o: Offer ) => {
    try {
      const r = await api< { url: string } >( '/clicks', {
        method: 'POST',
        body: { provider_id: o.providerId, provider_offer_id: o.providerOfferId },
      } );
      window.open( r.url, '_blank', 'noopener' );
    } catch {
      // ignore
    }
  };

  return (
    <Card>
      <CardHeader>
        <h3 style={ { margin: 0 } }>{ __( 'Offers', 'simple-reward-offerwall' ) }</h3>
      </CardHeader>
      <CardBody>
        { loading && <Spinner /> }
        { ! loading && offers.length === 0 && (
          <p>{ __( 'No offers available right now.', 'simple-reward-offerwall' ) }</p>
        ) }
        <Flex justify="flex-start" gap={ 3 } wrap align="stretch">
          { offers.map( ( o ) => {
            const icon = o.icons?.large || o.icons?.mid || o.icons?.small || '';
            return (
              <div
                key={ `${ o.providerId }:${ o.providerOfferId }` }
                style={ {
                  border: '1px solid #ddd',
                  borderRadius: 8,
                  padding: 16,
                  width: 220,
                  display: 'flex',
                  flexDirection: 'column',
                  gap: 8,
                } }
              >
                <Flex justify="flex-start" gap={ 2 } align="center">
                  { icon && (
                    <img
                      src={ icon }
                      alt=""
                      width={ 40 }
                      height={ 40 }
                      style={ { borderRadius: 8, objectFit: 'cover' } }
                    />
                  ) }
                  <strong>{ o.name }</strong>
                </Flex>
                { typeof o.tasks === 'string' && (
                  <p style={ { margin: 0, fontSize: 13 } }>{ o.tasks }</p>
                ) }
                <p style={ { margin: 0, fontSize: 13, opacity: 0.7 } }>
                  { [ o.device, o.os, o.country ].filter( Boolean ).join( ' · ' ) }
                </p>
                <div style={ { marginTop: 'auto' } }>
                  <strong>{ o.totalPayout }</strong>{ ' ' }
                  <span style={ { opacity: 0.7 } }>
                    { __( 'payout', 'simple-reward-offerwall' ) }
                  </span>
                </div>
                <Button variant="primary" onClick={ () => start( o ) }>
                  { __( 'Start', 'simple-reward-offerwall' ) }
                </Button>
              </div>
            );
          } ) }
        </Flex>
      </CardBody>
    </Card>
  );
};

interface Offerwall {
  id: number;
  name: string;
  type: string;
}

const Offerwalls = () => {
  const [ walls, setWalls ] = useState< Offerwall[] >( [] );
  const [ activeUrl, setActiveUrl ] = useState< string | null >( null );

  useEffect( () => {
    api< { offerwalls: Offerwall[] } >( '/offerwalls' )
      .then( ( r ) => setWalls( r.offerwalls || [] ) )
      .catch( () => setWalls( [] ) );
  }, [] );

  const open = async ( id: number ) => {
    try {
      const r = await api< { url: string } >( `/offerwalls/${ id }/url` );
      setActiveUrl( r.url );
    } catch {
      setActiveUrl( null );
    }
  };

  return (
    <Card>
      <CardHeader>
        <h3 style={ { margin: 0 } }>{ __( 'Offerwalls', 'simple-reward-offerwall' ) }</h3>
      </CardHeader>
      <CardBody>
        { walls.length === 0 && (
          <p>{ __( 'No offerwalls available yet.', 'simple-reward-offerwall' ) }</p>
        ) }
        <Flex justify="flex-start" gap={ 2 } wrap>
          { walls.map( ( w ) => (
            <Button key={ w.id } variant="secondary" onClick={ () => open( w.id ) }>
              { w.name }
            </Button>
          ) ) }
        </Flex>
        { activeUrl && (
          <div style={ { marginTop: 16 } }>
            <iframe
              title="offerwall"
              src={ activeUrl }
              style={ { width: '100%', height: 600, border: '1px solid #ddd', borderRadius: 8 } }
            />
          </div>
        ) }
      </CardBody>
    </Card>
  );
};

interface RewardRow {
  id: number;
  coins_value: number;
  status: string;
  provider_name: string | null;
  created_at: string;
}

const Rewards = () => {
  const [ rewards, setRewards ] = useState< RewardRow[] >( [] );

  useEffect( () => {
    api< { rewards: RewardRow[] } >( '/me/rewards' )
      .then( ( r ) => setRewards( r.rewards || [] ) )
      .catch( () => setRewards( [] ) );
  }, [] );

  return (
    <Card>
      <CardHeader>
        <h3 style={ { margin: 0 } }>{ __( 'Rewards', 'simple-reward-offerwall' ) }</h3>
      </CardHeader>
      <CardBody>
        { rewards.length === 0 ? (
          <p>
            { __( 'No rewards yet — complete an offer to earn coins.', 'simple-reward-offerwall' ) }
          </p>
        ) : (
          <table style={ { width: '100%', borderCollapse: 'collapse' } }>
            <thead>
              <tr style={ { textAlign: 'left', borderBottom: '1px solid #ddd' } }>
                <th>{ __( 'Provider', 'simple-reward-offerwall' ) }</th>
                <th>{ __( 'Coins', 'simple-reward-offerwall' ) }</th>
                <th>{ __( 'Status', 'simple-reward-offerwall' ) }</th>
              </tr>
            </thead>
            <tbody>
              { rewards.map( ( r ) => (
                <tr key={ r.id } style={ { borderBottom: '1px solid #f0f0f0' } }>
                  <td>{ r.provider_name || '—' }</td>
                  <td>{ r.coins_value }</td>
                  <td>{ r.status }</td>
                </tr>
              ) ) }
            </tbody>
          </table>
        ) }
      </CardBody>
    </Card>
  );
};

/* ------------------------------------------------------------------ */

interface Payout {
  id: number;
  name: string;
  valueCoins: number;
  valueMoney: number;
  currency: string;
}

const Payouts = ( { onRedeemed }: { onRedeemed: () => void } ) => {
  const [ payouts, setPayouts ] = useState< Payout[] >( [] );
  const [ balance, setBalance ] = useState< number >( 0 );
  const [ error, setError ] = useState< string | null >( null );
  const [ busyId, setBusyId ] = useState< number | null >( null );

  const load = () =>
    api< { payouts: Payout[]; balance: number } >( '/payouts' )
      .then( ( r ) => {
        setPayouts( r.payouts || [] );
        setBalance( r.balance );
      } )
      .catch( () => setPayouts( [] ) );

  useEffect( () => {
    load();
  }, [] );

  const redeem = async ( id: number ) => {
    setBusyId( id );
    setError( null );
    try {
      await api( '/redemptions', { method: 'POST', body: { payout_id: id } } );
      await load();
      onRedeemed();
    } catch ( e ) {
      setError(
        e instanceof ApiError ? e.message : __( 'Could not redeem.', 'simple-reward-offerwall' )
      );
    } finally {
      setBusyId( null );
    }
  };

  return (
    <Card>
      <CardHeader>
        <h3 style={ { margin: 0 } }>{ __( 'Redeem coins', 'simple-reward-offerwall' ) }</h3>
      </CardHeader>
      <CardBody>
        <ErrorNotice message={ error } />
        { payouts.length === 0 ? (
          <p>{ __( 'No rewards available to redeem yet.', 'simple-reward-offerwall' ) }</p>
        ) : (
          <Flex justify="flex-start" gap={ 3 } wrap align="stretch">
            { payouts.map( ( p ) => {
              const affordable = balance >= p.valueCoins;
              return (
                <div
                  key={ p.id }
                  style={ {
                    border: '1px solid #ddd',
                    borderRadius: 8,
                    padding: 16,
                    width: 180,
                    opacity: affordable ? 1 : 0.6,
                  } }
                >
                  <strong>{ p.name }</strong>
                  <p style={ { margin: '8px 0' } }>
                    { p.valueCoins } { __( 'coins', 'simple-reward-offerwall' ) }
                  </p>
                  <Button
                    variant="primary"
                    disabled={ ! affordable || busyId === p.id }
                    isBusy={ busyId === p.id }
                    onClick={ () => redeem( p.id ) }
                  >
                    { __( 'Redeem', 'simple-reward-offerwall' ) }
                  </Button>
                </div>
              );
            } ) }
          </Flex>
        ) }
      </CardBody>
    </Card>
  );
};

interface RedemptionRow {
  id: number;
  coins_spent: number;
  status: string;
  payout_name: string | null;
  created_at: string;
}

const MyRedemptions = ( { tick }: { tick: number } ) => {
  const [ rows, setRows ] = useState< RedemptionRow[] >( [] );

  useEffect( () => {
    api< { redemptions: RedemptionRow[] } >( '/me/redemptions' )
      .then( ( r ) => setRows( r.redemptions || [] ) )
      .catch( () => setRows( [] ) );
  }, [ tick ] );

  if ( rows.length === 0 ) {
    return null;
  }

  return (
    <Card>
      <CardHeader>
        <h3 style={ { margin: 0 } }>{ __( 'My redemptions', 'simple-reward-offerwall' ) }</h3>
      </CardHeader>
      <CardBody>
        <table style={ { width: '100%', borderCollapse: 'collapse' } }>
          <thead>
            <tr style={ { textAlign: 'left', borderBottom: '1px solid #ddd' } }>
              <th>{ __( 'Reward', 'simple-reward-offerwall' ) }</th>
              <th>{ __( 'Coins', 'simple-reward-offerwall' ) }</th>
              <th>{ __( 'Status', 'simple-reward-offerwall' ) }</th>
            </tr>
          </thead>
          <tbody>
            { rows.map( ( r ) => (
              <tr key={ r.id } style={ { borderBottom: '1px solid #f0f0f0' } }>
                <td>{ r.payout_name || '—' }</td>
                <td>{ r.coins_spent }</td>
                <td>{ r.status }</td>
              </tr>
            ) ) }
          </tbody>
        </table>
      </CardBody>
    </Card>
  );
};

/* ------------------------------------------------------------------ */

interface TicketSummary {
  id: number;
  subject: string;
  status: string;
}

interface TicketMessage {
  id: number;
  authorType: string;
  body: string;
}

interface TicketDetail {
  id: number;
  subject: string;
  status: string;
  messages: TicketMessage[];
}

const Support = () => {
  const [ tickets, setTickets ] = useState< TicketSummary[] >( [] );
  const [ openId, setOpenId ] = useState< number | null >( null );
  const [ creating, setCreating ] = useState( false );

  const reload = () =>
    api< { tickets: TicketSummary[] } >( '/support/tickets' )
      .then( ( r ) => setTickets( r.tickets || [] ) )
      .catch( () => setTickets( [] ) );

  useEffect( () => {
    reload();
  }, [] );

  return (
    <Card>
      <CardHeader>
        <Flex justify="space-between">
          <h3 style={ { margin: 0 } }>{ __( 'Support', 'simple-reward-offerwall' ) }</h3>
          <Button variant="secondary" onClick={ () => setCreating( ! creating ) }>
            { creating
              ? __( 'Cancel', 'simple-reward-offerwall' )
              : __( 'New ticket', 'simple-reward-offerwall' ) }
          </Button>
        </Flex>
      </CardHeader>
      <CardBody>
        { creating && (
          <NewTicket
            onCreated={ ( id ) => {
              setCreating( false );
              reload();
              setOpenId( id );
            } }
          />
        ) }

        { tickets.length === 0 && ! creating && (
          <p>{ __( 'No tickets yet.', 'simple-reward-offerwall' ) }</p>
        ) }

        { tickets.map( ( t ) => (
          <div key={ t.id } style={ { borderBottom: '1px solid #f0f0f0', padding: '8px 0' } }>
            <Flex justify="space-between">
              <span>
                <strong>{ t.subject }</strong> <em style={ { opacity: 0.6 } }>({ t.status })</em>
              </span>
              <Button variant="link" onClick={ () => setOpenId( openId === t.id ? null : t.id ) }>
                { openId === t.id
                  ? __( 'Hide', 'simple-reward-offerwall' )
                  : __( 'View', 'simple-reward-offerwall' ) }
              </Button>
            </Flex>
            { openId === t.id && <Thread ticketId={ t.id } onReplied={ reload } /> }
          </div>
        ) ) }
      </CardBody>
    </Card>
  );
};

const NewTicket = ( { onCreated }: { onCreated: ( id: number ) => void } ) => {
  const [ subject, setSubject ] = useState( '' );
  const [ message, setMessage ] = useState( '' );
  const [ error, setError ] = useState< string | null >( null );
  const [ busy, setBusy ] = useState( false );

  const submit = async () => {
    setError( null );
    setBusy( true );
    try {
      const r = await api< { ticket: { id: number } } >( '/support/tickets', {
        method: 'POST',
        body: { subject, message },
      } );
      onCreated( r.ticket.id );
    } catch ( e ) {
      setError(
        e instanceof ApiError
          ? e.message
          : __( 'Could not create ticket.', 'simple-reward-offerwall' )
      );
    } finally {
      setBusy( false );
    }
  };

  return (
    <div style={ { marginBottom: 16 } }>
      <ErrorNotice message={ error } />
      <TextControl
        label={ __( 'Subject', 'simple-reward-offerwall' ) }
        value={ subject }
        onChange={ setSubject }
        __nextHasNoMarginBottom
        __next40pxDefaultSize
      />
      <TextareaControl
        label={ __( 'Message', 'simple-reward-offerwall' ) }
        value={ message }
        onChange={ setMessage }
        rows={ 3 }
        __nextHasNoMarginBottom
      />
      <div style={ { marginTop: 8 } }>
        <Button variant="primary" onClick={ submit } isBusy={ busy } disabled={ busy }>
          { __( 'Create ticket', 'simple-reward-offerwall' ) }
        </Button>
      </div>
    </div>
  );
};

const Thread = ( { ticketId, onReplied }: { ticketId: number; onReplied: () => void } ) => {
  const [ ticket, setTicket ] = useState< TicketDetail | null >( null );
  const [ reply, setReply ] = useState( '' );
  const [ busy, setBusy ] = useState( false );

  const load = () =>
    api< { ticket: TicketDetail } >( `/support/tickets/${ ticketId }` )
      .then( ( r ) => setTicket( r.ticket ) )
      .catch( () => setTicket( null ) );

  useEffect( () => {
    load();
  }, [ ticketId ] );

  const send = async () => {
    if ( ! reply.trim() ) {
      return;
    }
    setBusy( true );
    try {
      await api( `/support/tickets/${ ticketId }/messages`, {
        method: 'POST',
        body: { message: reply },
      } );
      setReply( '' );
      await load();
      onReplied();
    } finally {
      setBusy( false );
    }
  };

  if ( ! ticket ) {
    return null;
  }

  return (
    <div style={ { margin: '8px 0 12px', paddingLeft: 12, borderLeft: '3px solid #e0e0e0' } }>
      <div style={ { display: 'flex', flexDirection: 'column', gap: 6, marginBottom: 8 } }>
        { ticket.messages.map( ( m ) => {
          const staff = m.authorType !== 'user';
          return (
            <div
              key={ m.id }
              style={ {
                alignSelf: staff ? 'flex-start' : 'flex-end',
                maxWidth: '80%',
                background: staff ? '#e7f0ff' : '#f2f2f2',
                borderRadius: 8,
                padding: '6px 10px',
                fontSize: 14,
              } }
            >
              <div style={ { fontSize: 11, opacity: 0.6 } }>
                { staff
                  ? __( 'Support', 'simple-reward-offerwall' )
                  : __( 'You', 'simple-reward-offerwall' ) }
              </div>
              { m.body }
            </div>
          );
        } ) }
      </div>
      { ticket.status !== 'closed' && (
        <Flex justify="flex-start" gap={ 2 } align="flex-end">
          <div style={ { flex: 1 } }>
            <TextControl
              value={ reply }
              onChange={ setReply }
              placeholder={ __( 'Write a reply…', 'simple-reward-offerwall' ) }
              __nextHasNoMarginBottom
              __next40pxDefaultSize
            />
          </div>
          <Button variant="primary" onClick={ send } isBusy={ busy } disabled={ busy }>
            { __( 'Reply', 'simple-reward-offerwall' ) }
          </Button>
        </Flex>
      ) }
    </div>
  );
};

/* ------------------------------------------------------------------ */

const container = document.getElementById( 'simple-ro-user-root' );
if ( container ) {
  createRoot( container ).render( <App /> );
}
