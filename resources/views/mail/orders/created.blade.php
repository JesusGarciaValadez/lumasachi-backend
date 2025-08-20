@component('mail::message')
# New Order Created: #{{ $order->id }}

A new order has been created.

**Order Details:**
- Order ID: {{ $order->id }}
- Title: {{ $order->title }}
- Customer: {{ $order->customer->full_name }}
- Assigned to: {{ $order->assignedTo->full_name ?? 'Unassigned' }}
- Status: {{ $order->status->value }}
- Priority: {{ $order->priority->value }}

@component('mail::button', ['url' => route('orders.show', $order)])
View Order
@endcomponent

Thank you,<br>
{{ config('app.name') }}
@endcomponent

