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


namespace App\Models{
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
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Model|\Eloquent $attachable
 * @property-read \App\Models\User $uploadedBy
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Attachment documents()
 * @method static \Database\Factories\AttachmentFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Attachment images()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Attachment newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Attachment newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Attachment query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Attachment whereAttachableId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Attachment whereAttachableType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Attachment whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Attachment whereFileName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Attachment whereFilePath($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Attachment whereFileSize($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Attachment whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Attachment whereMimeType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Attachment whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Attachment whereUploadedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Attachment whereUuid($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	final class IdeHelperAttachment {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $uuid
 * @property string $name
 * @property string|null $description
 * @property bool $is_active
 * @property int $sort_order
 * @property string|null $color
 * @property int $created_by
 * @property int $updated_by
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @property-read \App\Models\User $creator
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Order> $orders
 * @property-read int|null $orders_count
 * @property-read \App\Models\User $updater
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Category active()
 * @method static \Database\Factories\CategoryFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Category forCompany($companyId)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Category newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Category newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Category ordered()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Category query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Category whereColor($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Category whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Category whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Category whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Category whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Category whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Category whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Category whereSortOrder($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Category whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Category whereUpdatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Category whereUuid($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperCategory {}
}

namespace App\Models{
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
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\User> $activeUsers
 * @property-read int|null $active_users_count
 * @property-read string $full_address
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\User> $users
 * @property-read int|null $users_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Company active()
 * @method static \Database\Factories\CompanyFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Company inactive()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Company newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Company newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Company query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Company whereAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Company whereCity($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Company whereContactEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Company whereContactPerson($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Company whereContactPhone($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Company whereCountry($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Company whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Company whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Company whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Company whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Company whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Company whereLogo($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Company whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Company whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Company wherePhone($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Company wherePostalCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Company whereSettings($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Company whereState($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Company whereTaxId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Company whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Company whereUuid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Company whereWebsite($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperCompany {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $uuid
 * @property int $customer_id
 * @property int $assigned_to
 * @property string $title
 * @property string $description
 * @property \App\Enums\OrderStatus $status
 * @property \App\Enums\OrderPriority $priority
 * @property \Carbon\CarbonImmutable|null $estimated_completion
 * @property \Carbon\CarbonImmutable|null $actual_completion
 * @property string|null $notes
 * @property int $created_by
 * @property int|null $updated_by
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @property-read \App\Models\User $assignedTo
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Attachment> $attachments
 * @property-read int|null $attachments_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Category> $categories
 * @property-read int|null $categories_count
 * @property-read \App\Models\User $createdBy
 * @property-read \App\Models\User $customer
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\OrderItem> $items
 * @property-read int|null $items_count
 * @property-read \App\Models\OrderMotorInfo|null $motorInfo
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\OrderHistory> $orderHistories
 * @property-read int|null $order_histories_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\OrderService> $services
 * @property-read int|null $services_count
 * @property-read \App\Models\User|null $updatedBy
 * @method static \Database\Factories\OrderFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereActualCompletion($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereAssignedTo($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereCustomerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereEstimatedCompletion($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order wherePriority($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereUpdatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereUuid($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	final class IdeHelperOrder {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $uuid
 * @property int $order_id
 * @property string $field_changed
 * @property mixed|null $old_value
 * @property mixed|null $new_value
 * @property string|null $comment
 * @property int $created_by
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Attachment> $attachments
 * @property-read int|null $attachments_count
 * @property-read \App\Models\User $createdBy
 * @property-read string $description
 * @property-read \App\Models\Order $order
 * @property-read \App\Models\Order $orders
 * @method static \Database\Factories\OrderHistoryFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderHistory newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderHistory newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderHistory query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderHistory whereComment($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderHistory whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderHistory whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderHistory whereFieldChanged($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderHistory whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderHistory whereNewValue($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderHistory whereOldValue($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderHistory whereOrderId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderHistory whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderHistory whereUuid($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	final class IdeHelperOrderHistory {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $uuid
 * @property int $order_id
 * @property \App\Enums\OrderItemType $item_type
 * @property bool $is_received
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\OrderItemComponent> $components
 * @property-read int|null $components_count
 * @property-read \App\Models\Order $order
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\OrderService> $services
 * @property-read int|null $services_count
 * @method static \Database\Factories\OrderItemFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItem newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItem newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItem query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItem whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItem whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItem whereIsReceived($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItem whereItemType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItem whereOrderId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItem whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItem whereUuid($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	final class IdeHelperOrderItem {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $uuid
 * @property int $order_item_id
 * @property string $component_name
 * @property bool $is_received
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @property-read \App\Models\OrderItem $orderItem
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItemComponent newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItemComponent newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItemComponent query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItemComponent whereComponentName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItemComponent whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItemComponent whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItemComponent whereIsReceived($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItemComponent whereOrderItemId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItemComponent whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItemComponent whereUuid($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	final class IdeHelperOrderItemComponent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $uuid
 * @property int $order_id
 * @property string|null $brand
 * @property string|null $liters
 * @property string|null $year
 * @property string|null $model
 * @property string|null $cylinder_count
 * @property numeric|null $down_payment
 * @property numeric|null $total_cost
 * @property bool $is_fully_paid
 * @property string|null $center_torque
 * @property string|null $rod_torque
 * @property string|null $first_gap
 * @property string|null $second_gap
 * @property string|null $third_gap
 * @property string|null $center_clearance
 * @property string|null $rod_clearance
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @property-read float $remaining_balance
 * @property-read \App\Models\Order $order
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderMotorInfo newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderMotorInfo newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderMotorInfo query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderMotorInfo whereBrand($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderMotorInfo whereCenterClearance($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderMotorInfo whereCenterTorque($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderMotorInfo whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderMotorInfo whereCylinderCount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderMotorInfo whereDownPayment($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderMotorInfo whereFirstGap($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderMotorInfo whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderMotorInfo whereIsFullyPaid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderMotorInfo whereLiters($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderMotorInfo whereModel($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderMotorInfo whereOrderId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderMotorInfo whereRodClearance($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderMotorInfo whereRodTorque($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderMotorInfo whereSecondGap($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderMotorInfo whereThirdGap($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderMotorInfo whereTotalCost($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderMotorInfo whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderMotorInfo whereUuid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderMotorInfo whereYear($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	final class IdeHelperOrderMotorInfo {}
}

namespace App\Models{
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
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @property-read \App\Models\ServiceCatalog|null $catalogItem
 * @property-read \App\Models\OrderItem $orderItem
 * @method static \Database\Factories\OrderServiceFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderService newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderService newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderService query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderService whereBasePrice($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderService whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderService whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderService whereIsAuthorized($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderService whereIsBudgeted($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderService whereIsCompleted($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderService whereMeasurement($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderService whereNetPrice($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderService whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderService whereOrderItemId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderService whereServiceKey($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderService whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderService whereUuid($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	final class IdeHelperOrderService {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $uuid
 * @property string $service_key
 * @property string $service_name_key
 * @property \App\Enums\OrderItemType $item_type
 * @property numeric $base_price
 * @property numeric $tax_percentage
 * @property bool $requires_measurement
 * @property bool $is_active
 * @property int $display_order
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @property-read float $net_price
 * @property-read string $service_name
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ServiceCatalog active()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ServiceCatalog forItemType(\App\Enums\OrderItemType $type)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ServiceCatalog newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ServiceCatalog newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ServiceCatalog query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ServiceCatalog whereBasePrice($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ServiceCatalog whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ServiceCatalog whereDisplayOrder($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ServiceCatalog whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ServiceCatalog whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ServiceCatalog whereItemType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ServiceCatalog whereRequiresMeasurement($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ServiceCatalog whereServiceKey($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ServiceCatalog whereServiceNameKey($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ServiceCatalog whereTaxPercentage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ServiceCatalog whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ServiceCatalog whereUuid($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	final class IdeHelperServiceCatalog {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $uuid
 * @property int|null $company_id
 * @property string $first_name
 * @property string $last_name
 * @property string $email
 * @property \Carbon\CarbonImmutable|null $email_verified_at
 * @property string $password
 * @property \App\Enums\UserRole $role
 * @property string|null $phone_number
 * @property bool $is_active
 * @property string|null $notes
 * @property \App\Enums\UserType|null $type
 * @property string|null $preferences
 * @property string|null $remember_token
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Order> $assignedOrders
 * @property-read int|null $assigned_orders_count
 * @property-read \App\Models\Company|null $company
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Order> $createdOrders
 * @property-read int|null $created_orders_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Order> $customerOrders
 * @property-read int|null $customer_orders_count
 * @property-read string $full_name
 * @property-read \Illuminate\Notifications\DatabaseNotificationCollection<int, \Illuminate\Notifications\DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Laravel\Sanctum\PersonalAccessToken> $tokens
 * @property-read int|null $tokens_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User customers()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User employees()
 * @method static \Database\Factories\UserFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmailVerifiedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereFirstName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereLastName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User wherePassword($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User wherePhoneNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User wherePreferences($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereRememberToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereRole($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereUuid($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperUser {}
}

