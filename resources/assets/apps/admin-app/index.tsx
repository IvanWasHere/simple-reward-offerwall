/**
 * Simple Reward Offerwall — admin SPA (Phase 2 slice).
 *
 * Provider CRUD, per-provider S2S callback config, and the reward-approval queue.
 * Mounted via [simple_ro_admin_app]. Authorization is enforced server-side by the
 * REST Guard (role: admin); this bundle only reflects it.
 */
import {
  Button,
  Card,
  CardBody,
  CardHeader,
  Flex,
  Notice,
  SelectControl,
  Spinner,
  TextControl,
  TextareaControl,
} from '@wordpress/components';
import { createRoot, useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

import { api, ApiError } from '../shared/api';

interface Me {
  id: number;
  email: string;
  type: string;
}

const App = () => {
  const [ loading, setLoading ] = useState( true );
  const [ me, setMe ] = useState< Me | null >( null );

  const load = () =>
    api< { user: Me } >( '/auth/me' )
      .then( ( r ) => setMe( r.user ) )
      .catch( () => setMe( null ) )
      .finally( () => setLoading( false ) );

  useEffect( () => {
    load();
  }, [] );

  if ( loading ) {
    return (
      <Flex justify="center" style={ { padding: 48 } }>
        <Spinner />
      </Flex>
    );
  }

  if ( ! me || me.type !== 'admin' ) {
    return <Login onDone={ () => load() } isAdminGate={ !! me } />;
  }

  return <AdminHome me={ me } />;
};

const Login = ( { onDone, isAdminGate }: { onDone: () => void; isAdminGate: boolean } ) => {
  const [ email, setEmail ] = useState( '' );
  const [ password, setPassword ] = useState( '' );
  const [ error, setError ] = useState< string | null >( null );
  const [ busy, setBusy ] = useState( false );

  const submit = async () => {
    setBusy( true );
    setError( null );
    try {
      await api( '/auth/login', { method: 'POST', body: { email, password } } );
      onDone();
    } catch ( e ) {
      setError( e instanceof ApiError ? e.message : 'Error' );
    } finally {
      setBusy( false );
    }
  };

  return (
    <div style={ { maxWidth: 420, margin: '32px auto' } }>
      <Card>
        <CardHeader>
          <h2 style={ { margin: 0 } }>{ __( 'Admin sign in', 'simple-reward-offerwall' ) }</h2>
        </CardHeader>
        <CardBody>
          { isAdminGate && (
            <Notice status="warning" isDismissible={ false }>
              { __( 'This account is not an administrator.', 'simple-reward-offerwall' ) }
            </Notice>
          ) }
          { error && (
            <Notice status="error" isDismissible={ false }>
              { error }
            </Notice>
          ) }
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
        </CardBody>
      </Card>
    </div>
  );
};

const AdminHome = ( { me }: { me: Me } ) => {
  const logout = async () => {
    try {
      await api( '/auth/session', { method: 'DELETE' } );
    } finally {
      window.location.reload();
    }
  };

  return (
    <div style={ { maxWidth: 960, margin: '32px auto' } }>
      <Flex justify="space-between" style={ { marginBottom: 16 } }>
        <h1 style={ { margin: 0 } }>{ __( 'Offerwall Admin', 'simple-reward-offerwall' ) }</h1>
        <Flex justify="flex-end" gap={ 2 }>
          <span style={ { opacity: 0.7 } }>{ me.email }</span>
          <Button variant="secondary" onClick={ logout }>
            { __( 'Sign out', 'simple-reward-offerwall' ) }
          </Button>
        </Flex>
      </Flex>

      <Providers />
      <div style={ { marginTop: 24 } }>
        <RewardsQueue />
      </div>
    </div>
  );
};

/* ------------------------------------------------------------------ */

interface Provider {
  id: number;
  name: string;
  type: string;
  coinRate: number;
  status: string;
  callbackCount: number | null;
}

const Providers = () => {
  const [ providers, setProviders ] = useState< Provider[] >( [] );
  const [ selected, setSelected ] = useState< number | null >( null );

  const reload = () =>
    api< { providers: Provider[] } >( '/admin/providers' )
      .then( ( r ) => setProviders( r.providers || [] ) )
      .catch( () => setProviders( [] ) );

  useEffect( () => {
    reload();
  }, [] );

  return (
    <Card>
      <CardHeader>
        <h2 style={ { margin: 0 } }>{ __( 'Providers', 'simple-reward-offerwall' ) }</h2>
      </CardHeader>
      <CardBody>
        <table style={ { width: '100%', borderCollapse: 'collapse', marginBottom: 16 } }>
          <thead>
            <tr style={ { textAlign: 'left', borderBottom: '1px solid #ddd' } }>
              <th>{ __( 'Name', 'simple-reward-offerwall' ) }</th>
              <th>{ __( 'Type', 'simple-reward-offerwall' ) }</th>
              <th>{ __( 'Coin rate', 'simple-reward-offerwall' ) }</th>
              <th>{ __( 'Callbacks', 'simple-reward-offerwall' ) }</th>
              <th />
            </tr>
          </thead>
          <tbody>
            { providers.map( ( p ) => (
              <tr key={ p.id } style={ { borderBottom: '1px solid #f0f0f0' } }>
                <td>{ p.name }</td>
                <td>{ p.type }</td>
                <td>{ p.coinRate }</td>
                <td>{ p.callbackCount ?? 0 }</td>
                <td style={ { textAlign: 'right' } }>
                  <Button
                    variant="link"
                    onClick={ () => setSelected( selected === p.id ? null : p.id ) }
                  >
                    { selected === p.id
                      ? __( 'Hide callbacks', 'simple-reward-offerwall' )
                      : __( 'Callbacks', 'simple-reward-offerwall' ) }
                  </Button>
                </td>
              </tr>
            ) ) }
          </tbody>
        </table>

        { selected && <Callbacks providerId={ selected } onChange={ reload } /> }

        <div style={ { marginTop: 16 } }>
          <ProviderForm onCreated={ reload } />
        </div>
      </CardBody>
    </Card>
  );
};

const ProviderForm = ( { onCreated }: { onCreated: () => void } ) => {
  const [ name, setName ] = useState( '' );
  const [ type, setType ] = useState( 'iframe' );
  const [ url, setUrl ] = useState( '' );
  const [ adslot, setAdslot ] = useState( '' );
  const [ coinRate, setCoinRate ] = useState( '1' );
  const [ macros, setMacros ] = useState(
    '{\n  "{macro_user_id}": "user_id",\n  "{macro_adslot_id}": "adslot_id",\n  "{macro_session_id}": "session_id"\n}'
  );
  const [ error, setError ] = useState< string | null >( null );
  const [ busy, setBusy ] = useState( false );

  const submit = async () => {
    setError( null );
    let macrosObj: Record< string, string > = {};
    try {
      macrosObj = macros.trim() ? JSON.parse( macros ) : {};
    } catch {
      setError( __( 'Macros must be valid JSON.', 'simple-reward-offerwall' ) );
      return;
    }
    setBusy( true );
    try {
      await api( '/admin/providers', {
        method: 'POST',
        body: {
          name,
          type,
          url,
          adslot_id: adslot,
          coin_rate: parseFloat( coinRate ) || 0,
          macros: macrosObj,
        },
      } );
      setName( '' );
      setUrl( '' );
      onCreated();
    } catch ( e ) {
      setError( e instanceof ApiError ? e.message : 'Error' );
    } finally {
      setBusy( false );
    }
  };

  return (
    <details>
      <summary style={ { cursor: 'pointer', fontWeight: 600 } }>
        { __( 'Add provider', 'simple-reward-offerwall' ) }
      </summary>
      <div style={ { marginTop: 12 } }>
        { error && (
          <Notice status="error" isDismissible={ false }>
            { error }
          </Notice>
        ) }
        <TextControl
          label={ __( 'Name', 'simple-reward-offerwall' ) }
          value={ name }
          onChange={ setName }
          __nextHasNoMarginBottom
          __next40pxDefaultSize
        />
        <SelectControl
          label={ __( 'Type', 'simple-reward-offerwall' ) }
          value={ type }
          options={ [
            { label: 'iframe', value: 'iframe' },
            { label: 'offerwall_api', value: 'offerwall_api' },
            { label: 'static_api', value: 'static_api' },
          ] }
          onChange={ setType }
          __nextHasNoMarginBottom
          __next40pxDefaultSize
        />
        <TextControl
          label={ __( 'URL template', 'simple-reward-offerwall' ) }
          value={ url }
          onChange={ setUrl }
          __nextHasNoMarginBottom
          __next40pxDefaultSize
        />
        <TextControl
          label={ __( 'Ad slot ID', 'simple-reward-offerwall' ) }
          value={ adslot }
          onChange={ setAdslot }
          __nextHasNoMarginBottom
          __next40pxDefaultSize
        />
        <TextControl
          label={ __( 'Coin rate (coins per 1.00)', 'simple-reward-offerwall' ) }
          value={ coinRate }
          onChange={ setCoinRate }
          __nextHasNoMarginBottom
          __next40pxDefaultSize
        />
        <TextareaControl
          label={ __( 'Macros (JSON)', 'simple-reward-offerwall' ) }
          value={ macros }
          onChange={ setMacros }
          rows={ 5 }
          __nextHasNoMarginBottom
        />
        <div style={ { marginTop: 12 } }>
          <Button variant="primary" onClick={ submit } isBusy={ busy } disabled={ busy }>
            { __( 'Create provider', 'simple-reward-offerwall' ) }
          </Button>
        </div>
      </div>
    </details>
  );
};

/* ------------------------------------------------------------------ */

interface Callback {
  id: number;
  name: string;
  callbackUrl: string;
  signatureAlgo: string;
  active: boolean;
}

const Callbacks = ( { providerId, onChange }: { providerId: number; onChange: () => void } ) => {
  const [ callbacks, setCallbacks ] = useState< Callback[] >( [] );

  const reload = () =>
    api< { callbacks: Callback[] } >( `/admin/providers/${ providerId }/callbacks` )
      .then( ( r ) => setCallbacks( r.callbacks || [] ) )
      .catch( () => setCallbacks( [] ) );

  useEffect( () => {
    reload();
  }, [ providerId ] );

  return (
    <div style={ { background: '#fafafa', padding: 16, borderRadius: 8, marginBottom: 16 } }>
      <h4 style={ { marginTop: 0 } }>{ __( 'Callbacks', 'simple-reward-offerwall' ) }</h4>
      { callbacks.map( ( c ) => (
        <div key={ c.id } style={ { marginBottom: 8 } }>
          <strong>{ c.name }</strong> ({ c.signatureAlgo }) —{ ' ' }
          <code style={ { fontSize: 12 } }>{ c.callbackUrl }</code>
        </div>
      ) ) }
      <CallbackForm
        providerId={ providerId }
        onCreated={ () => {
          reload();
          onChange();
        } }
      />
    </div>
  );
};

const CallbackForm = ( {
  providerId,
  onCreated,
}: {
  providerId: number;
  onCreated: () => void;
} ) => {
  const [ name, setName ] = useState( 'Postback' );
  const [ sigParam, setSigParam ] = useState( 'sig' );
  const [ algo, setAlgo ] = useState( 'hmac_sha256' );
  const [ secret, setSecret ] = useState( '' );
  const [ paramMap, setParamMap ] = useState(
    '{\n  "transaction_id": "txn",\n  "user_id": "uid",\n  "amount": "payout"\n}'
  );
  const [ error, setError ] = useState< string | null >( null );
  const [ busy, setBusy ] = useState( false );

  const submit = async () => {
    setError( null );
    let mapObj: Record< string, string > = {};
    try {
      mapObj = paramMap.trim() ? JSON.parse( paramMap ) : {};
    } catch {
      setError( __( 'Param map must be valid JSON.', 'simple-reward-offerwall' ) );
      return;
    }
    setBusy( true );
    try {
      await api( `/admin/providers/${ providerId }/callbacks`, {
        method: 'POST',
        body: {
          name,
          signature_param: sigParam,
          signature_algo: algo,
          signature_source: 'ordered_params',
          secret,
          param_map: mapObj,
        },
      } );
      setSecret( '' );
      onCreated();
    } catch ( e ) {
      setError( e instanceof ApiError ? e.message : 'Error' );
    } finally {
      setBusy( false );
    }
  };

  return (
    <details style={ { marginTop: 8 } }>
      <summary style={ { cursor: 'pointer', fontWeight: 600 } }>
        { __( 'Add callback', 'simple-reward-offerwall' ) }
      </summary>
      <div style={ { marginTop: 12 } }>
        { error && (
          <Notice status="error" isDismissible={ false }>
            { error }
          </Notice>
        ) }
        <TextControl
          label={ __( 'Name', 'simple-reward-offerwall' ) }
          value={ name }
          onChange={ setName }
          __nextHasNoMarginBottom
          __next40pxDefaultSize
        />
        <TextControl
          label={ __( 'Signature param', 'simple-reward-offerwall' ) }
          value={ sigParam }
          onChange={ setSigParam }
          __nextHasNoMarginBottom
          __next40pxDefaultSize
        />
        <SelectControl
          label={ __( 'Signature algo', 'simple-reward-offerwall' ) }
          value={ algo }
          options={ [
            { label: 'hmac_sha256', value: 'hmac_sha256' },
            { label: 'md5_concat', value: 'md5_concat' },
            { label: 'none (testing)', value: 'none' },
          ] }
          onChange={ setAlgo }
          __nextHasNoMarginBottom
          __next40pxDefaultSize
        />
        <TextControl
          label={ __( 'Secret', 'simple-reward-offerwall' ) }
          value={ secret }
          onChange={ setSecret }
          __nextHasNoMarginBottom
          __next40pxDefaultSize
        />
        <TextareaControl
          label={ __( 'Param map (JSON)', 'simple-reward-offerwall' ) }
          value={ paramMap }
          onChange={ setParamMap }
          rows={ 5 }
          __nextHasNoMarginBottom
        />
        <div style={ { marginTop: 12 } }>
          <Button variant="primary" onClick={ submit } isBusy={ busy } disabled={ busy }>
            { __( 'Create callback', 'simple-reward-offerwall' ) }
          </Button>
        </div>
      </div>
    </details>
  );
};

/* ------------------------------------------------------------------ */

interface RewardRow {
  id: number;
  user_email: string | null;
  coins_value: number;
  provider_name: string | null;
  transaction_id: string | null;
}

const RewardsQueue = () => {
  const [ rewards, setRewards ] = useState< RewardRow[] >( [] );

  const reload = () =>
    api< { rewards: RewardRow[] } >( '/admin/rewards?status=pending' )
      .then( ( r ) => setRewards( r.rewards || [] ) )
      .catch( () => setRewards( [] ) );

  useEffect( () => {
    reload();
  }, [] );

  const act = async ( id: number, action: 'approve' | 'reject' ) => {
    try {
      await api( `/admin/rewards/${ id }/${ action }`, { method: 'POST' } );
    } finally {
      reload();
    }
  };

  return (
    <Card>
      <CardHeader>
        <h2 style={ { margin: 0 } }>{ __( 'Pending rewards', 'simple-reward-offerwall' ) }</h2>
      </CardHeader>
      <CardBody>
        { rewards.length === 0 ? (
          <p>{ __( 'Nothing pending.', 'simple-reward-offerwall' ) }</p>
        ) : (
          <table style={ { width: '100%', borderCollapse: 'collapse' } }>
            <thead>
              <tr style={ { textAlign: 'left', borderBottom: '1px solid #ddd' } }>
                <th>{ __( 'User', 'simple-reward-offerwall' ) }</th>
                <th>{ __( 'Provider', 'simple-reward-offerwall' ) }</th>
                <th>{ __( 'Coins', 'simple-reward-offerwall' ) }</th>
                <th>{ __( 'Txn', 'simple-reward-offerwall' ) }</th>
                <th />
              </tr>
            </thead>
            <tbody>
              { rewards.map( ( r ) => (
                <tr key={ r.id } style={ { borderBottom: '1px solid #f0f0f0' } }>
                  <td>{ r.user_email || r.id }</td>
                  <td>{ r.provider_name || '—' }</td>
                  <td>{ r.coins_value }</td>
                  <td>
                    <code style={ { fontSize: 12 } }>{ r.transaction_id }</code>
                  </td>
                  <td style={ { textAlign: 'right' } }>
                    <Flex justify="flex-end" gap={ 1 }>
                      <Button variant="primary" onClick={ () => act( r.id, 'approve' ) }>
                        { __( 'Approve', 'simple-reward-offerwall' ) }
                      </Button>
                      <Button
                        variant="secondary"
                        isDestructive
                        onClick={ () => act( r.id, 'reject' ) }
                      >
                        { __( 'Reject', 'simple-reward-offerwall' ) }
                      </Button>
                    </Flex>
                  </td>
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

const container = document.getElementById( 'simple-ro-admin-root' );
if ( container ) {
  createRoot( container ).render( <App /> );
}
