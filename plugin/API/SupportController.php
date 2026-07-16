<?php

namespace SimpleRO\API;

if (!defined('ABSPATH')) {
  exit();
}

use SimpleRO\API\Auth\Guard;
use SimpleRO\Services\LedgerService;
use SimpleRO\WPBones\Routing\API\RestController;

/**
 * SupportController — support tickets and threaded messages.
 *
 * User routes (Guard::role('user')): list/create my tickets.
 * Shared routes (Guard::authenticated()): view a ticket / post a message — the
 *   owner or any staff member; ownership is enforced here.
 * Staff routes (Guard::role('support')): the queue, assignment, status, and a
 *   read-only user context. `admin` is a superset of `support`.
 */
class SupportController extends RestController
{
  /* ------------------------------- user ------------------------------- */

  public function myTickets()
  {
    global $wpdb;
    $user = Guard::user($this->request);
    $t = $wpdb->prefix . 'ro_support_requests';

    $rows = $wpdb->get_results($wpdb->prepare(
      "SELECT id, subject, status, assigned_to, last_message_at, created_at
         FROM {$t} WHERE user_id = %d ORDER BY COALESCE(last_message_at, created_at) DESC LIMIT 200",
      (int) $user->id
    ));

    return $this->response(['tickets' => $rows ?: []]);
  }

  public function create()
  {
    global $wpdb;
    $user = Guard::user($this->request);

    $subject = sanitize_text_field((string) $this->request->get_param('subject'));
    $message = sanitize_textarea_field((string) $this->request->get_param('message'));

    if ($subject === '' || $message === '') {
      return $this->responseError('ro_invalid', __('Subject and message are required.', 'simple-reward-offerwall'), 422);
    }

    $now = gmdate('Y-m-d H:i:s');
    $wpdb->insert(
      $wpdb->prefix . 'ro_support_requests',
      ['user_id' => (int) $user->id, 'subject' => $subject, 'status' => 'open', 'last_message_at' => $now, 'created_at' => $now, 'updated_at' => $now],
      ['%d', '%s', '%s', '%s', '%s', '%s']
    );
    $ticketId = (int) $wpdb->insert_id;

    $this->insertMessage($ticketId, (int) $user->id, $user->type, $message);

    return $this->response(['ticket' => $this->ticketWithMessages($ticketId)], 201);
  }

  /* ------------------------------ shared ------------------------------ */

  public function show()
  {
    $user = Guard::user($this->request);
    $ticketId = (int) $this->request->get_param('id');

    $ticket = $this->findTicket($ticketId);
    if (!$ticket) {
      return $this->responseError('ro_not_found', __('Ticket not found.', 'simple-reward-offerwall'), 404);
    }
    if (!$this->canAccess($user, $ticket)) {
      return $this->responseError('ro_forbidden', __('Not allowed.', 'simple-reward-offerwall'), 403);
    }

    return $this->response(['ticket' => $this->ticketWithMessages($ticketId)]);
  }

  public function postMessage()
  {
    global $wpdb;
    $user = Guard::user($this->request);
    $ticketId = (int) $this->request->get_param('id');
    $message = sanitize_textarea_field((string) $this->request->get_param('message'));

    if ($message === '') {
      return $this->responseError('ro_invalid', __('Message is required.', 'simple-reward-offerwall'), 422);
    }

    $ticket = $this->findTicket($ticketId);
    if (!$ticket) {
      return $this->responseError('ro_not_found', __('Ticket not found.', 'simple-reward-offerwall'), 404);
    }
    if (!$this->canAccess($user, $ticket)) {
      return $this->responseError('ro_forbidden', __('Not allowed.', 'simple-reward-offerwall'), 403);
    }

    $this->insertMessage($ticketId, (int) $user->id, $user->type, $message);

    // A staff reply moves the ticket to 'pending' (awaiting the user); a user reply
    // (re)opens it. Explicit close is via setStatus.
    $newStatus = $this->isStaff($user) ? 'pending' : 'open';
    $wpdb->update(
      $wpdb->prefix . 'ro_support_requests',
      ['status' => $newStatus, 'last_message_at' => gmdate('Y-m-d H:i:s'), 'updated_at' => gmdate('Y-m-d H:i:s')],
      ['id' => $ticketId],
      ['%s', '%s', '%s'],
      ['%d']
    );

    return $this->response(['ticket' => $this->ticketWithMessages($ticketId)]);
  }

  /* ------------------------------- staff ------------------------------ */

  public function queue()
  {
    global $wpdb;
    $status = (string) $this->request->get_param('status');
    $t = $wpdb->prefix . 'ro_support_requests';
    $u = $wpdb->prefix . 'ro_users';

    $where = '';
    $args = [];
    if (in_array($status, ['open', 'pending', 'closed'], true)) {
      $where = 'WHERE t.status = %s';
      $args[] = $status;
    }

    $sql = "SELECT t.id, t.subject, t.status, t.user_id, t.assigned_to, t.last_message_at,
                   u.email AS user_email, a.email AS assignee_email
              FROM {$t} t
              LEFT JOIN {$u} u ON u.id = t.user_id
              LEFT JOIN {$u} a ON a.id = t.assigned_to
              {$where}
             ORDER BY COALESCE(t.last_message_at, t.created_at) DESC
             LIMIT 500";

    $rows = $args ? $wpdb->get_results($wpdb->prepare($sql, $args)) : $wpdb->get_results($sql);

    return $this->response(['tickets' => $rows ?: []]);
  }

  public function assign()
  {
    global $wpdb;
    $me = Guard::user($this->request);
    $ticketId = (int) $this->request->get_param('id');

    $target = $this->request->get_param('support_user_id');
    $assignee = ($target === null || (int) $target === 0) ? (int) $me->id : (int) $target;

    // The assignee must be staff.
    $type = $wpdb->get_var($wpdb->prepare("SELECT type FROM {$wpdb->prefix}ro_users WHERE id = %d", $assignee));
    if (!in_array($type, ['support', 'admin'], true)) {
      return $this->responseError('ro_invalid', __('Assignee must be a support or admin user.', 'simple-reward-offerwall'), 422);
    }

    if (!$this->findTicket($ticketId)) {
      return $this->responseError('ro_not_found', __('Ticket not found.', 'simple-reward-offerwall'), 404);
    }

    $wpdb->update(
      $wpdb->prefix . 'ro_support_requests',
      ['assigned_to' => $assignee, 'updated_at' => gmdate('Y-m-d H:i:s')],
      ['id' => $ticketId],
      ['%d', '%s'],
      ['%d']
    );

    return $this->response(['ticket' => ['id' => $ticketId, 'assignedTo' => $assignee]]);
  }

  public function setStatus()
  {
    global $wpdb;
    $ticketId = (int) $this->request->get_param('id');
    $status = (string) $this->request->get_param('status');

    if (!in_array($status, ['open', 'pending', 'closed'], true)) {
      return $this->responseError('ro_invalid', __('Invalid status.', 'simple-reward-offerwall'), 422);
    }
    if (!$this->findTicket($ticketId)) {
      return $this->responseError('ro_not_found', __('Ticket not found.', 'simple-reward-offerwall'), 404);
    }

    $wpdb->update(
      $wpdb->prefix . 'ro_support_requests',
      ['status' => $status, 'updated_at' => gmdate('Y-m-d H:i:s')],
      ['id' => $ticketId],
      ['%s', '%s'],
      ['%d']
    );

    return $this->response(['ticket' => ['id' => $ticketId, 'status' => $status]]);
  }

  public function userContext()
  {
    global $wpdb;
    $userId = (int) $this->request->get_param('id');

    $u = $wpdb->get_row($wpdb->prepare(
      "SELECT id, email, type, status, display_name, created_at FROM {$wpdb->prefix}ro_users WHERE id = %d",
      $userId
    ));
    if (!$u) {
      return $this->responseError('ro_not_found', __('User not found.', 'simple-reward-offerwall'), 404);
    }

    $rewards = $wpdb->get_results($wpdb->prepare(
      "SELECT id, coins_value, status, created_at FROM {$wpdb->prefix}ro_rewards WHERE user_id = %d ORDER BY id DESC LIMIT 10",
      $userId
    ));

    return $this->response([
      'user' => [
        'id'          => (int) $u->id,
        'email'       => $u->email,
        'type'        => $u->type,
        'status'      => $u->status,
        'displayName' => $u->display_name,
        'balance'     => LedgerService::balance($userId),
        'createdAt'   => $u->created_at,
      ],
      'recentRewards' => $rewards ?: [],
    ]);
  }

  /* ------------------------------ helpers ----------------------------- */

  private function isStaff(object $user): bool
  {
    return in_array($user->type, ['support', 'admin'], true);
  }

  private function canAccess(object $user, object $ticket): bool
  {
    return $this->isStaff($user) || (int) $ticket->user_id === (int) $user->id;
  }

  private function findTicket(int $id): ?object
  {
    global $wpdb;
    return $wpdb->get_row($wpdb->prepare(
      "SELECT * FROM {$wpdb->prefix}ro_support_requests WHERE id = %d LIMIT 1",
      $id
    ));
  }

  private function insertMessage(int $ticketId, int $authorId, string $authorType, string $body): void
  {
    global $wpdb;
    $wpdb->insert(
      $wpdb->prefix . 'ro_support_messages',
      ['ticket_id' => $ticketId, 'author_id' => $authorId, 'author_type' => $authorType, 'body' => $body, 'created_at' => gmdate('Y-m-d H:i:s')],
      ['%d', '%d', '%s', '%s', '%s']
    );
  }

  private function ticketWithMessages(int $ticketId): array
  {
    global $wpdb;
    $ticket = $this->findTicket($ticketId);
    if (!$ticket) {
      return [];
    }

    $messages = $wpdb->get_results($wpdb->prepare(
      "SELECT id, author_id, author_type, body, created_at
         FROM {$wpdb->prefix}ro_support_messages WHERE ticket_id = %d ORDER BY id ASC",
      $ticketId
    ));

    return [
      'id'            => (int) $ticket->id,
      'userId'        => (int) $ticket->user_id,
      'subject'       => $ticket->subject,
      'status'        => $ticket->status,
      'assignedTo'    => (int) $ticket->assigned_to,
      'lastMessageAt' => $ticket->last_message_at,
      'createdAt'     => $ticket->created_at,
      'messages'      => array_map(function ($m) {
        return [
          'id'         => (int) $m->id,
          'authorId'   => (int) $m->author_id,
          'authorType' => $m->author_type,
          'body'       => $m->body,
          'createdAt'  => $m->created_at,
        ];
      }, $messages ?: []),
    ];
  }
}
