import { Badge } from './ui';

const STATUS_COLOR = { pending: 'amber', confirmed: 'blue', shipped: 'blue', completed: 'green', cancelled: 'gray' };
const PAYMENT_COLOR = { unpaid: 'amber', paid: 'green', refunded: 'gray' };

export function OrderStatusBadge({ status, label }) {
    return <Badge color={STATUS_COLOR[status] || 'gray'}>{label}</Badge>;
}

const COUPON_COLOR = { active: 'green', scheduled: 'blue', expired: 'gray', used_up: 'amber', disabled: 'gray' };

export function CouponStatusBadge({ status, label }) {
    return <Badge color={COUPON_COLOR[status] || 'gray'}>{label}</Badge>;
}

export function PaymentStatusBadge({ status, label }) {
    return <Badge color={PAYMENT_COLOR[status] || 'gray'}>{label}</Badge>;
}
