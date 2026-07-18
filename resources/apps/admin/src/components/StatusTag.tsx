const STATUS: Record<string, [string, string]> = {
  active: ['tag-green', 'Active'],
  inactive: ['tag-gray', 'Inactive'],
  pending: ['tag-amber', 'Pending'],
  approved: ['tag-green', 'Approved'],
  rejected: ['tag-red', 'Rejected'],
  resolved: ['tag-blue', 'Resolved'],
  open: ['tag-red', 'Open'],
  closed: ['tag-gray', 'Closed'],
  blocked: ['tag-red', 'Blocked'],
  banned: ['tag-red', 'Banned'],
  enabled: ['tag-green', 'Enabled'],
  disabled: ['tag-gray', 'Disabled'],
};

const PROVIDER_TYPE: Record<string, [string, string]> = {
  static_api: ['tag-blue', 'Static API'],
  offerwall_api: ['tag-green', 'Offerwall API'],
  iframe: ['tag-amber', 'Iframe'],
};

const ROLE: Record<string, [string, string]> = {
  admin: ['tag-red', 'Admin'],
  support: ['tag-amber', 'Support'],
  user: ['tag-gray', 'User'],
};

function Tag({ map, value }: { map: Record<string, [string, string]>; value: string }) {
  const [cls, label] = map[value] ?? ['tag-gray', value];
  return <span className={'tag ' + cls}>{label}</span>;
}

export const StatusTag = ({ status }: { status: string }) => <Tag map={STATUS} value={status} />;
export const ProviderTypeTag = ({ type }: { type: string }) => (
  <Tag map={PROVIDER_TYPE} value={type} />
);
export const RoleTag = ({ role }: { role: string }) => <Tag map={ROLE} value={role} />;
