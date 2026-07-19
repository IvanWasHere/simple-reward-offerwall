/**
 * Simple Reward Offerwall — support SPA (Phase 6).
 *
 * The support agent queue: browse tickets, assign, reply, change status, and view
 * a read-only snapshot of the user. Mounted via [simplerewardoffer_support_app].
 * Authorization is enforced server-side (role: support; admin is a superset).
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

interface QueueTicket {
  id: string;
  subject: string;
  status: string;
  user_email: string | null;
  assignee_email: string | null;
  user_id: string;
}

interface Message {
  id: number;
  authorType: string;
  body: string;
  createdAt: string;
}

interface Ticket {
  id: number;
  userId: number;
  subject: string;
  status: string;
  assignedTo: number;
  messages: Message[];
}

const App = () => {
  const [ loading, setLoading ] = useState( true );
  const [ me, setMe ] = useState< Me | null >( null );

  const load = () =>
    api< { user: Me | null } >( '/auth/me' )
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

  const isStaff = me && ( me.type === 'support' || me.type === 'admin' );
  if ( ! isStaff ) {
    return <Login onDone={ load } gate={ !! me } />;
  }

  return <SupportHome me={ me as Me } />;
};

const Login = ( { onDone, gate }: { onDone: () => void; gate: boolean } ) => {
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
          <h2 style={ { margin: 0 } }>{ __( 'Support sign in', 'simple-reward-offerwall' ) }</h2>
        </CardHeader>
        <CardBody>
          { gate && (
            <Notice status="warning" isDismissible={ false }>
              { __( 'This account is not a support agent.', 'simple-reward-offerwall' ) }
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

const SupportHome = ( { me }: { me: Me } ) => {
  const [ tickets, setTickets ] = useState< QueueTicket[] >( [] );
  const [ filter, setFilter ] = useState( '' );
  const [ openId, setOpenId ] = useState< number | null >( null );

  const reload = () => {
    const qs = filter ? `?status=${ filter }` : '';
    api< { tickets: QueueTicket[] } >( `/support/queue${ qs }` )
      .then( ( r ) => setTickets( r.tickets || [] ) )
      .catch( () => setTickets( [] ) );
  };

  useEffect( () => {
    reload();
  }, [ filter ] );

  const logout = async () => {
    try {
      await api( '/auth/session', { method: 'DELETE' } );
    } finally {
      window.location.reload();
    }
  };

  return (
    <div style={ { maxWidth: 900, margin: '32px auto' } }>
      <Flex justify="space-between" style={ { marginBottom: 16 } }>
        <h1 style={ { margin: 0 } }>{ __( 'Support', 'simple-reward-offerwall' ) }</h1>
        <Flex justify="flex-end" gap={ 2 }>
          <span style={ { opacity: 0.7 } }>{ me.email }</span>
          <Button variant="secondary" onClick={ logout }>
            { __( 'Sign out', 'simple-reward-offerwall' ) }
          </Button>
        </Flex>
      </Flex>

      <Card>
        <CardHeader>
          <Flex justify="space-between">
            <h2 style={ { margin: 0 } }>{ __( 'Ticket queue', 'simple-reward-offerwall' ) }</h2>
            <div style={ { minWidth: 160 } }>
              <SelectControl
                value={ filter }
                options={ [
                  { label: __( 'All', 'simple-reward-offerwall' ), value: '' },
                  { label: 'open', value: 'open' },
                  { label: 'pending', value: 'pending' },
                  { label: 'closed', value: 'closed' },
                ] }
                onChange={ setFilter }
                __nextHasNoMarginBottom
                __next40pxDefaultSize
              />
            </div>
          </Flex>
        </CardHeader>
        <CardBody>
          { tickets.length === 0 ? (
            <p>{ __( 'No tickets.', 'simple-reward-offerwall' ) }</p>
          ) : (
            <table style={ { width: '100%', borderCollapse: 'collapse' } }>
              <thead>
                <tr style={ { textAlign: 'left', borderBottom: '1px solid #ddd' } }>
                  <th>{ __( 'Subject', 'simple-reward-offerwall' ) }</th>
                  <th>{ __( 'User', 'simple-reward-offerwall' ) }</th>
                  <th>{ __( 'Status', 'simple-reward-offerwall' ) }</th>
                  <th>{ __( 'Assignee', 'simple-reward-offerwall' ) }</th>
                  <th />
                </tr>
              </thead>
              <tbody>
                { tickets.map( ( t ) => (
                  <tr key={ t.id } style={ { borderBottom: '1px solid #f0f0f0' } }>
                    <td>{ t.subject }</td>
                    <td>{ t.user_email }</td>
                    <td>{ t.status }</td>
                    <td>{ t.assignee_email || '—' }</td>
                    <td style={ { textAlign: 'right' } }>
                      <Button variant="link" onClick={ () => setOpenId( Number( t.id ) ) }>
                        { __( 'Open', 'simple-reward-offerwall' ) }
                      </Button>
                    </td>
                  </tr>
                ) ) }
              </tbody>
            </table>
          ) }
        </CardBody>
      </Card>

      { openId && (
        <div style={ { marginTop: 24 } }>
          <TicketView
            ticketId={ openId }
            onChanged={ reload }
            onClose={ () => setOpenId( null ) }
          />
        </div>
      ) }
    </div>
  );
};

const TicketView = ( {
  ticketId,
  onChanged,
  onClose,
}: {
  ticketId: number;
  onChanged: () => void;
  onClose: () => void;
} ) => {
  const [ ticket, setTicket ] = useState< Ticket | null >( null );
  const [ reply, setReply ] = useState( '' );
  const [ busy, setBusy ] = useState( false );

  const load = () =>
    api< { ticket: Ticket } >( `/support/tickets/${ ticketId }` )
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
      onChanged();
    } finally {
      setBusy( false );
    }
  };

  const assignSelf = async () => {
    await api( `/support/tickets/${ ticketId }/assign`, { method: 'POST', body: {} } );
    await load();
    onChanged();
  };

  const setStatus = async ( status: string ) => {
    await api( `/support/tickets/${ ticketId }`, { method: 'PUT', body: { status } } );
    await load();
    onChanged();
  };

  if ( ! ticket ) {
    return <Spinner />;
  }

  return (
    <Card>
      <CardHeader>
        <Flex justify="space-between">
          <h3 style={ { margin: 0 } }>
            #{ ticket.id } · { ticket.subject }{ ' ' }
            <em style={ { opacity: 0.6 } }>({ ticket.status })</em>
          </h3>
          <Button variant="tertiary" onClick={ onClose }>
            { __( 'Close view', 'simple-reward-offerwall' ) }
          </Button>
        </Flex>
      </CardHeader>
      <CardBody>
        <Flex justify="flex-start" gap={ 2 } style={ { marginBottom: 16 } }>
          <Button variant="secondary" onClick={ assignSelf }>
            { __( 'Assign to me', 'simple-reward-offerwall' ) }
          </Button>
          <Button variant="tertiary" onClick={ () => setStatus( 'pending' ) }>
            { __( 'Mark pending', 'simple-reward-offerwall' ) }
          </Button>
          <Button variant="tertiary" onClick={ () => setStatus( 'closed' ) }>
            { __( 'Close ticket', 'simple-reward-offerwall' ) }
          </Button>
        </Flex>

        <div style={ { display: 'flex', flexDirection: 'column', gap: 8, marginBottom: 16 } }>
          { ticket.messages.map( ( m ) => {
            const staff = m.authorType !== 'user';
            return (
              <div
                key={ m.id }
                style={ {
                  alignSelf: staff ? 'flex-end' : 'flex-start',
                  maxWidth: '75%',
                  background: staff ? '#e7f0ff' : '#f2f2f2',
                  borderRadius: 8,
                  padding: '8px 12px',
                } }
              >
                <div style={ { fontSize: 11, opacity: 0.6 } }>{ m.authorType }</div>
                { m.body }
              </div>
            );
          } ) }
        </div>

        <TextareaControl
          label={ __( 'Reply', 'simple-reward-offerwall' ) }
          value={ reply }
          onChange={ setReply }
          rows={ 3 }
          __nextHasNoMarginBottom
        />
        <div style={ { marginTop: 8 } }>
          <Button variant="primary" onClick={ send } isBusy={ busy } disabled={ busy }>
            { __( 'Send reply', 'simple-reward-offerwall' ) }
          </Button>
        </div>
      </CardBody>
    </Card>
  );
};

/* ------------------------------------------------------------------ */

const container = document.getElementById( 'simplerewardoffer-support-root' );
if ( container ) {
  createRoot( container ).render( <App /> );
}
