<?php

// @formatter:off
// phpcs:ignoreFile
/**
 * A helper file for your Eloquent Models
 * Copy the phpDocs from this file to the correct Model,
 * And remove them from this file, to prevent double declarations.
 *
 * @author Barry vd. Heuvel <barryvdh@gmail.com>
 */


namespace App\Models{use AllowDynamicProperties;use Carbon\CarbonImmutable;use Database\Factories\AttachmentFactory;use Eloquent;use Illuminate\Database\Eloquent\Builder;use Illuminate\Database\Eloquent\Model;
/**
 * @property int $id
 * @property string $uuid
 * @property string $attachable_type
 * @property int $attachable_id
 * @property string $file_name
 * @property string $file_path
 * @property int $file_size
 * @property string $mime_type
 * @property int $uploaded_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Model $attachable
 * @property-read User $uploadedBy
 * @method static Builder<static>|Attachment documents()
 * @method static AttachmentFactory factory($count = null, $state = [])
 * @method static Builder<static>|Attachment images()
 * @method static Builder<static>|Attachment newModelQuery()
 * @method static Builder<static>|Attachment newQuery()
 * @method static Builder<static>|Attachment query()
 * @method static Builder<static>|Attachment whereAttachableId($value)
 * @method static Builder<static>|Attachment whereAttachableType($value)
 * @method static Builder<static>|Attachment whereCreatedAt($value)
 * @method static Builder<static>|Attachment whereFileName($value)
 * @method static Builder<static>|Attachment whereFilePath($value)
 * @method static Builder<static>|Attachment whereFileSize($value)
 * @method static Builder<static>|Attachment whereId($value)
 * @method static Builder<static>|Attachment whereMimeType($value)
 * @method static Builder<static>|Attachment whereUpdatedAt($value)
 * @method static Builder<static>|Attachment whereUploadedBy($value)
 * @method static Builder<static>|Attachment whereUuid($value)
 * @mixin Eloquent
 */
	#[AllowDynamicProperties]
	final class IdeHelperAttachment {}
}

namespace App\Models{use AllowDynamicProperties;use Carbon\CarbonImmutable;use Database\Factories\CompanyFactory;use Eloquent;use Illuminate\Database\Eloquent\Builder;use Illuminate\Database\Eloquent\Collection;
/**
 * @property int $id
 * @property string $uuid
 * @property string $name
 * @property string $email
 * @property string $phone
 * @property string $address
 * @property string $city
 * @property string $state
 * @property string $postal_code
 * @property string $country
 * @property string|null $website
 * @property string|null $logo
 * @property string|null $tax_id
 * @property string|null $contact_person
 * @property string|null $contact_email
 * @property string|null $contact_phone
 * @property string|null $notes
 * @property array<array-key, mixed>|null $settings
 * @property string|null $description
 * @property bool $is_active
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Collection<int, User> $activeUsers
 * @property-read int|null $active_users_count
 * @property-read string $full_address
 * @property-read Collection<int, User> $users
 * @property-read int|null $users_count
 * @method static Builder<static>|Company active()
 * @method static CompanyFactory factory($count = null, $state = [])
 * @method static Builder<static>|Company inactive()
 * @method static Builder<static>|Company newModelQuery()
 * @method static Builder<static>|Company newQuery()
 * @method static Builder<static>|Company query()
 * @method static Builder<static>|Company whereAddress($value)
 * @method static Builder<static>|Company whereCity($value)
 * @method static Builder<static>|Company whereContactEmail($value)
 * @method static Builder<static>|Company whereContactPerson($value)
 * @method static Builder<static>|Company whereContactPhone($value)
 * @method static Builder<static>|Company whereCountry($value)
 * @method static Builder<static>|Company whereCreatedAt($value)
 * @method static Builder<static>|Company whereDescription($value)
 * @method static Builder<static>|Company whereEmail($value)
 * @method static Builder<static>|Company whereId($value)
 * @method static Builder<static>|Company whereIsActive($value)
 * @method static Builder<static>|Company whereLogo($value)
 * @method static Builder<static>|Company whereName($value)
 * @method static Builder<static>|Company whereNotes($value)
 * @method static Builder<static>|Company wherePhone($value)
 * @method static Builder<static>|Company wherePostalCode($value)
 * @method static Builder<static>|Company whereSettings($value)
 * @method static Builder<static>|Company whereState($value)
 * @method static Builder<static>|Company whereTaxId($value)
 * @method static Builder<static>|Company whereUpdatedAt($value)
 * @method static Builder<static>|Company whereUuid($value)
 * @method static Builder<static>|Company whereWebsite($value)
 * @mixin Eloquent
 */
	#[AllowDynamicProperties]
	final class IdeHelperCompany {}
}

namespace App\Models{use AllowDynamicProperties;use App\Enums\OrderDispositionStatus;use App\Enums\OrderLifecycleStatus;use App\Enums\OrderPriority;use App\Enums\OrderStatus;use Carbon\CarbonImmutable;use Database\Factories\OrderFactory;use Eloquent;use Illuminate\Database\Eloquent\Builder;use Illuminate\Database\Eloquent\Collection;
/**
 * @property int $id
 * @property string $uuid
 * @property int $customer_id
 * @property int $assigned_to
 * @property string $title
 * @property string $description
 * @property OrderPriority $priority
 * @property CarbonImmutable|null $estimated_completion
 * @property CarbonImmutable|null $actual_completion
 * @property string|null $notes
 * @property int $created_by
 * @property int|null $updated_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property OrderLifecycleStatus $lifecycle_status
 * @property OrderDispositionStatus|null $disposition_status
 * @property-read User $assignedTo
 * @property-read Collection<int, Attachment> $attachments
 * @property-read int|null $attachments_count
 * @property-read User $createdBy
 * @property-read User $customer
 * @property OrderStatus|null $status
 * @property-read Collection<int, OrderItem> $items
 * @property-read int|null $items_count
 * @property-read OrderMotorInfo|null $motorInfo
 * @property-read Collection<int, OrderHistory> $orderHistories
 * @property-read int|null $order_histories_count
 * @property-read Collection<int, OrderPayment> $payments
 * @property-read int|null $payments_count
 * @property-read Collection<int, OrderRefund> $refunds
 * @property-read int|null $refunds_count
 * @property-read Collection<int, OrderService> $services
 * @property-read int|null $services_count
 * @property-read User|null $updatedBy
 * @method static OrderFactory factory($count = null, $state = [])
 * @method static Builder<static>|Order newModelQuery()
 * @method static Builder<static>|Order newQuery()
 * @method static Builder<static>|Order query()
 * @method static Builder<static>|Order whereActualCompletion($value)
 * @method static Builder<static>|Order whereAssignedTo($value)
 * @method static Builder<static>|Order whereCreatedAt($value)
 * @method static Builder<static>|Order whereCreatedBy($value)
 * @method static Builder<static>|Order whereCustomerId($value)
 * @method static Builder<static>|Order whereDescription($value)
 * @method static Builder<static>|Order whereDispositionStatus($value)
 * @method static Builder<static>|Order whereEstimatedCompletion($value)
 * @method static Builder<static>|Order whereId($value)
 * @method static Builder<static>|Order whereLifecycleStatus($value)
 * @method static Builder<static>|Order whereNotes($value)
 * @method static Builder<static>|Order wherePriority($value)
 * @method static Builder<static>|Order whereTitle($value)
 * @method static Builder<static>|Order whereUpdatedAt($value)
 * @method static Builder<static>|Order whereUpdatedBy($value)
 * @method static Builder<static>|Order whereUuid($value)
 * @mixin Eloquent
 */
	#[AllowDynamicProperties]
	final class IdeHelperOrder {}
}

namespace App\Models{use AllowDynamicProperties;use App\Enums\OrderHistoryEventType;use Carbon\CarbonImmutable;use Database\Factories\OrderHistoryFactory;use Eloquent;use Illuminate\Database\Eloquent\Builder;use Illuminate\Database\Eloquent\Collection;
/**
 * @property int $id
 * @property string $uuid
 * @property int $order_id
 * @property string $field_changed
 * @property mixed|null $old_value
 * @property mixed|null $new_value
 * @property string|null $comment
 * @property int $created_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property OrderHistoryEventType $event_type
 * @property-read Collection<int, Attachment> $attachments
 * @property-read int|null $attachments_count
 * @property-read User $createdBy
 * @property-read string $description
 * @property-read Order $order
 * @property-read Order $orders
 * @method static OrderHistoryFactory factory($count = null, $state = [])
 * @method static Builder<static>|OrderHistory newModelQuery()
 * @method static Builder<static>|OrderHistory newQuery()
 * @method static Builder<static>|OrderHistory query()
 * @method static Builder<static>|OrderHistory whereComment($value)
 * @method static Builder<static>|OrderHistory whereCreatedAt($value)
 * @method static Builder<static>|OrderHistory whereCreatedBy($value)
 * @method static Builder<static>|OrderHistory whereEventType($value)
 * @method static Builder<static>|OrderHistory whereFieldChanged($value)
 * @method static Builder<static>|OrderHistory whereId($value)
 * @method static Builder<static>|OrderHistory whereNewValue($value)
 * @method static Builder<static>|OrderHistory whereOldValue($value)
 * @method static Builder<static>|OrderHistory whereOrderId($value)
 * @method static Builder<static>|OrderHistory whereUpdatedAt($value)
 * @method static Builder<static>|OrderHistory whereUuid($value)
 * @mixin Eloquent
 */
	#[AllowDynamicProperties]
	final class IdeHelperOrderHistory {}
}

namespace App\Models{use AllowDynamicProperties;use App\Enums\OrderItemType;use Carbon\CarbonImmutable;use Database\Factories\OrderItemFactory;use Eloquent;use Illuminate\Database\Eloquent\Builder;use Illuminate\Database\Eloquent\Collection;
/**
 * @property int $id
 * @property string $uuid
 * @property int $order_id
 * @property OrderItemType $item_type
 * @property bool $is_received
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Collection<int, OrderItemComponent> $components
 * @property-read int|null $components_count
 * @property-read Order $order
 * @property-read Collection<int, OrderService> $services
 * @property-read int|null $services_count
 * @method static OrderItemFactory factory($count = null, $state = [])
 * @method static Builder<static>|OrderItem newModelQuery()
 * @method static Builder<static>|OrderItem newQuery()
 * @method static Builder<static>|OrderItem query()
 * @method static Builder<static>|OrderItem whereCreatedAt($value)
 * @method static Builder<static>|OrderItem whereId($value)
 * @method static Builder<static>|OrderItem whereIsReceived($value)
 * @method static Builder<static>|OrderItem whereItemType($value)
 * @method static Builder<static>|OrderItem whereOrderId($value)
 * @method static Builder<static>|OrderItem whereUpdatedAt($value)
 * @method static Builder<static>|OrderItem whereUuid($value)
 * @mixin Eloquent
 */
	#[AllowDynamicProperties]
	final class IdeHelperOrderItem {}
}

namespace App\Models{use AllowDynamicProperties;use Carbon\CarbonImmutable;use Database\Factories\OrderItemComponentFactory;use Eloquent;use Illuminate\Database\Eloquent\Builder;
/**
 * @property int $id
 * @property string $uuid
 * @property int $order_item_id
 * @property string $component_name
 * @property bool $is_received
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read OrderItem $orderItem
 * @method static OrderItemComponentFactory factory($count = null, $state = [])
 * @method static Builder<static>|OrderItemComponent newModelQuery()
 * @method static Builder<static>|OrderItemComponent newQuery()
 * @method static Builder<static>|OrderItemComponent query()
 * @method static Builder<static>|OrderItemComponent whereComponentName($value)
 * @method static Builder<static>|OrderItemComponent whereCreatedAt($value)
 * @method static Builder<static>|OrderItemComponent whereId($value)
 * @method static Builder<static>|OrderItemComponent whereIsReceived($value)
 * @method static Builder<static>|OrderItemComponent whereOrderItemId($value)
 * @method static Builder<static>|OrderItemComponent whereUpdatedAt($value)
 * @method static Builder<static>|OrderItemComponent whereUuid($value)
 * @mixin Eloquent
 */
	#[AllowDynamicProperties]
	final class IdeHelperOrderItemComponent {}
}

namespace App\Models{use AllowDynamicProperties;use Carbon\CarbonImmutable;use Database\Factories\OrderMotorInfoFactory;use Eloquent;use Illuminate\Database\Eloquent\Builder;
/**
 * @property int $id
 * @property string $uuid
 * @property int $order_id
 * @property string|null $brand
 * @property string|null $liters
 * @property string|null $year
 * @property string|null $model
 * @property string|null $cylinder_count
 * @property string|null $center_torque
 * @property string|null $rod_torque
 * @property string|null $first_gap
 * @property string|null $second_gap
 * @property string|null $third_gap
 * @property string|null $center_clearance
 * @property string|null $rod_clearance
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Order $order
 * @method static OrderMotorInfoFactory factory($count = null, $state = [])
 * @method static Builder<static>|OrderMotorInfo newModelQuery()
 * @method static Builder<static>|OrderMotorInfo newQuery()
 * @method static Builder<static>|OrderMotorInfo query()
 * @method static Builder<static>|OrderMotorInfo whereBrand($value)
 * @method static Builder<static>|OrderMotorInfo whereCenterClearance($value)
 * @method static Builder<static>|OrderMotorInfo whereCenterTorque($value)
 * @method static Builder<static>|OrderMotorInfo whereCreatedAt($value)
 * @method static Builder<static>|OrderMotorInfo whereCylinderCount($value)
 * @method static Builder<static>|OrderMotorInfo whereFirstGap($value)
 * @method static Builder<static>|OrderMotorInfo whereId($value)
 * @method static Builder<static>|OrderMotorInfo whereLiters($value)
 * @method static Builder<static>|OrderMotorInfo whereModel($value)
 * @method static Builder<static>|OrderMotorInfo whereOrderId($value)
 * @method static Builder<static>|OrderMotorInfo whereRodClearance($value)
 * @method static Builder<static>|OrderMotorInfo whereRodTorque($value)
 * @method static Builder<static>|OrderMotorInfo whereSecondGap($value)
 * @method static Builder<static>|OrderMotorInfo whereThirdGap($value)
 * @method static Builder<static>|OrderMotorInfo whereUpdatedAt($value)
 * @method static Builder<static>|OrderMotorInfo whereUuid($value)
 * @method static Builder<static>|OrderMotorInfo whereYear($value)
 * @mixin Eloquent
 */
	#[AllowDynamicProperties]
	final class IdeHelperOrderMotorInfo {}
}

namespace App\Models{use AllowDynamicProperties;use Carbon\CarbonImmutable;use Database\Factories\OrderPaymentFactory;use Eloquent;use Illuminate\Database\Eloquent\Builder;use Illuminate\Database\Eloquent\Collection;
/**
 * @property int $id
 * @property string $uuid
 * @property int $order_id
 * @property numeric $amount
 * @property CarbonImmutable $received_at
 * @property int|null $created_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read User|null $createdBy
 * @property-read Order $order
 * @property-read Collection<int, OrderRefund> $refunds
 * @property-read int|null $refunds_count
 * @method static OrderPaymentFactory factory($count = null, $state = [])
 * @method static Builder<static>|OrderPayment newModelQuery()
 * @method static Builder<static>|OrderPayment newQuery()
 * @method static Builder<static>|OrderPayment query()
 * @method static Builder<static>|OrderPayment whereAmount($value)
 * @method static Builder<static>|OrderPayment whereCreatedAt($value)
 * @method static Builder<static>|OrderPayment whereCreatedBy($value)
 * @method static Builder<static>|OrderPayment whereId($value)
 * @method static Builder<static>|OrderPayment whereOrderId($value)
 * @method static Builder<static>|OrderPayment whereReceivedAt($value)
 * @method static Builder<static>|OrderPayment whereUpdatedAt($value)
 * @method static Builder<static>|OrderPayment whereUuid($value)
 * @mixin Eloquent
 */
	#[AllowDynamicProperties]
	final class IdeHelperOrderPayment {}
}

namespace App\Models{use AllowDynamicProperties;use App\Enums\RefundStatus;use Carbon\CarbonImmutable;use Database\Factories\OrderRefundFactory;use Eloquent;use Illuminate\Database\Eloquent\Builder;
/**
 * @property int $id
 * @property string $uuid
 * @property int $order_id
 * @property int|null $source_payment_id
 * @property numeric $amount
 * @property RefundStatus $status
 * @property string $reason
 * @property int|null $requested_by
 * @property CarbonImmutable $requested_at
 * @property int|null $approved_by
 * @property CarbonImmutable|null $approved_at
 * @property int|null $rejected_by
 * @property CarbonImmutable|null $rejected_at
 * @property int|null $processed_by
 * @property CarbonImmutable|null $processed_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read User|null $approvedBy
 * @property-read Order $order
 * @property-read User|null $processedBy
 * @property-read User|null $rejectedBy
 * @property-read User|null $requestedBy
 * @property-read OrderPayment|null $sourcePayment
 * @method static OrderRefundFactory factory($count = null, $state = [])
 * @method static Builder<static>|OrderRefund newModelQuery()
 * @method static Builder<static>|OrderRefund newQuery()
 * @method static Builder<static>|OrderRefund query()
 * @method static Builder<static>|OrderRefund whereAmount($value)
 * @method static Builder<static>|OrderRefund whereApprovedAt($value)
 * @method static Builder<static>|OrderRefund whereApprovedBy($value)
 * @method static Builder<static>|OrderRefund whereCreatedAt($value)
 * @method static Builder<static>|OrderRefund whereId($value)
 * @method static Builder<static>|OrderRefund whereOrderId($value)
 * @method static Builder<static>|OrderRefund whereProcessedAt($value)
 * @method static Builder<static>|OrderRefund whereProcessedBy($value)
 * @method static Builder<static>|OrderRefund whereReason($value)
 * @method static Builder<static>|OrderRefund whereRejectedAt($value)
 * @method static Builder<static>|OrderRefund whereRejectedBy($value)
 * @method static Builder<static>|OrderRefund whereRequestedAt($value)
 * @method static Builder<static>|OrderRefund whereRequestedBy($value)
 * @method static Builder<static>|OrderRefund whereSourcePaymentId($value)
 * @method static Builder<static>|OrderRefund whereStatus($value)
 * @method static Builder<static>|OrderRefund whereUpdatedAt($value)
 * @method static Builder<static>|OrderRefund whereUuid($value)
 * @mixin Eloquent
 */
	#[AllowDynamicProperties]
	final class IdeHelperOrderRefund {}
}

namespace App\Models{use AllowDynamicProperties;use Carbon\CarbonImmutable;use Database\Factories\OrderServiceFactory;use Eloquent;use Illuminate\Database\Eloquent\Builder;
/**
 * @property int $id
 * @property string $uuid
 * @property int $order_item_id
 * @property string $service_key
 * @property string|null $measurement
 * @property bool $is_budgeted
 * @property bool $is_authorized
 * @property bool $is_completed
 * @property string|null $notes
 * @property numeric|null $base_price
 * @property numeric|null $net_price
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property int|null $updated_by
 * @property-read ServiceCatalog|null $catalogItem
 * @property-read OrderItem $orderItem
 * @method static OrderServiceFactory factory($count = null, $state = [])
 * @method static Builder<static>|OrderService newModelQuery()
 * @method static Builder<static>|OrderService newQuery()
 * @method static Builder<static>|OrderService query()
 * @method static Builder<static>|OrderService whereBasePrice($value)
 * @method static Builder<static>|OrderService whereCreatedAt($value)
 * @method static Builder<static>|OrderService whereId($value)
 * @method static Builder<static>|OrderService whereIsAuthorized($value)
 * @method static Builder<static>|OrderService whereIsBudgeted($value)
 * @method static Builder<static>|OrderService whereIsCompleted($value)
 * @method static Builder<static>|OrderService whereMeasurement($value)
 * @method static Builder<static>|OrderService whereNetPrice($value)
 * @method static Builder<static>|OrderService whereNotes($value)
 * @method static Builder<static>|OrderService whereOrderItemId($value)
 * @method static Builder<static>|OrderService whereServiceKey($value)
 * @method static Builder<static>|OrderService whereUpdatedAt($value)
 * @method static Builder<static>|OrderService whereUpdatedBy($value)
 * @method static Builder<static>|OrderService whereUuid($value)
 * @mixin Eloquent
 */
	#[AllowDynamicProperties]
	final class IdeHelperOrderService {}
}

namespace App\Models{use AllowDynamicProperties;use App\Enums\OrderItemType;use Carbon\CarbonImmutable;use Database\Factories\ServiceCatalogFactory;use Eloquent;use Illuminate\Database\Eloquent\Builder;
/**
 * @property int $id
 * @property string $uuid
 * @property string $service_key
 * @property string $service_name_key
 * @property OrderItemType $item_type
 * @property numeric $base_price
 * @property numeric $tax_percentage
 * @property bool $requires_measurement
 * @property bool $is_active
 * @property int $display_order
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read float $net_price
 * @property-read string $service_name
 * @method static Builder<static>|ServiceCatalog active()
 * @method static ServiceCatalogFactory factory($count = null, $state = [])
 * @method static Builder<static>|ServiceCatalog forItemType(OrderItemType $type)
 * @method static Builder<static>|ServiceCatalog newModelQuery()
 * @method static Builder<static>|ServiceCatalog newQuery()
 * @method static Builder<static>|ServiceCatalog query()
 * @method static Builder<static>|ServiceCatalog whereBasePrice($value)
 * @method static Builder<static>|ServiceCatalog whereCreatedAt($value)
 * @method static Builder<static>|ServiceCatalog whereDisplayOrder($value)
 * @method static Builder<static>|ServiceCatalog whereId($value)
 * @method static Builder<static>|ServiceCatalog whereIsActive($value)
 * @method static Builder<static>|ServiceCatalog whereItemType($value)
 * @method static Builder<static>|ServiceCatalog whereRequiresMeasurement($value)
 * @method static Builder<static>|ServiceCatalog whereServiceKey($value)
 * @method static Builder<static>|ServiceCatalog whereServiceNameKey($value)
 * @method static Builder<static>|ServiceCatalog whereTaxPercentage($value)
 * @method static Builder<static>|ServiceCatalog whereUpdatedAt($value)
 * @method static Builder<static>|ServiceCatalog whereUuid($value)
 * @mixin Eloquent
 */
	#[AllowDynamicProperties]
	final class IdeHelperServiceCatalog {}
}

namespace App\Models{use AllowDynamicProperties;use App\Enums\UserRole;use App\Enums\UserType;use Carbon\CarbonImmutable;use Database\Factories\UserFactory;use Eloquent;use Illuminate\Database\Eloquent\Builder;use Illuminate\Database\Eloquent\Collection;use Illuminate\Notifications\DatabaseNotification;use Illuminate\Notifications\DatabaseNotificationCollection;use Laravel\Sanctum\PersonalAccessToken;
/**
 * @property int $id
 * @property string $uuid
 * @property int|null $company_id
 * @property string $first_name
 * @property string $last_name
 * @property string $email
 * @property CarbonImmutable|null $email_verified_at
 * @property string $password
 * @property UserRole $role
 * @property string|null $phone_number
 * @property bool $is_active
 * @property string|null $notes
 * @property UserType|null $type
 * @property string|null $preferences
 * @property string|null $remember_token
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property string|null $locale
 * @property-read Collection<int, Order> $assignedOrders
 * @property-read int|null $assigned_orders_count
 * @property-read Company|null $company
 * @property-read Collection<int, Order> $createdOrders
 * @property-read int|null $created_orders_count
 * @property-read Collection<int, Order> $customerOrders
 * @property-read int|null $customer_orders_count
 * @property-read string $full_name
 * @property-read DatabaseNotificationCollection<int, DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 * @property-read Collection<int, PersonalAccessToken> $tokens
 * @property-read int|null $tokens_count
 * @method static Builder<static>|User customers()
 * @method static Builder<static>|User employees()
 * @method static UserFactory factory($count = null, $state = [])
 * @method static Builder<static>|User newModelQuery()
 * @method static Builder<static>|User newQuery()
 * @method static Builder<static>|User query()
 * @method static Builder<static>|User whereCompanyId($value)
 * @method static Builder<static>|User whereCreatedAt($value)
 * @method static Builder<static>|User whereEmail($value)
 * @method static Builder<static>|User whereEmailVerifiedAt($value)
 * @method static Builder<static>|User whereFirstName($value)
 * @method static Builder<static>|User whereId($value)
 * @method static Builder<static>|User whereIsActive($value)
 * @method static Builder<static>|User whereLastName($value)
 * @method static Builder<static>|User whereLocale($value)
 * @method static Builder<static>|User whereNotes($value)
 * @method static Builder<static>|User wherePassword($value)
 * @method static Builder<static>|User wherePhoneNumber($value)
 * @method static Builder<static>|User wherePreferences($value)
 * @method static Builder<static>|User whereRememberToken($value)
 * @method static Builder<static>|User whereRole($value)
 * @method static Builder<static>|User whereType($value)
 * @method static Builder<static>|User whereUpdatedAt($value)
 * @method static Builder<static>|User whereUuid($value)
 * @mixin Eloquent
 */
	#[AllowDynamicProperties]
	final class IdeHelperUser {}
}

