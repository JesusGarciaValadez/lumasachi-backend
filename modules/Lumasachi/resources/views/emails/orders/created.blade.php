@component('mail::message')
# New Order Created: #{{ $order->id }}

A new order has been created.
Order ID: {{ $order->id }}

@component('mail::button', ['url' => route('orders.show', $order)])
View Order
@endcomponent

Thank you for your business!
@endcomponent

