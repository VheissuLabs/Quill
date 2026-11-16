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
 * @property int $organization_id
 * @property string $invoice_prefix
 * @property numeric|null $default_rate
 * @property int $payment_terms_days
 * @property numeric|null $tax_rate
 * @property string|null $default_notes
 * @property string|null $default_terms
 * @property string|null $from_address
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Activitylog\Models\Activity> $activitiesAsSubject
 * @property-read int|null $activities_as_subject_count
 * @property-read \App\Models\Organization|null $organization
 * @method static \Database\Factories\BillingSettingFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingSetting newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingSetting newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingSetting query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingSetting whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingSetting whereDefaultNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingSetting whereDefaultRate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingSetting whereDefaultTerms($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingSetting whereFromAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingSetting whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingSetting whereInvoicePrefix($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingSetting whereOrganizationId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingSetting wherePaymentTermsDays($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingSetting whereTaxRate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingSetting whereUpdatedAt($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperBillingSetting {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $name
 * @property \App\Enums\IssueStatus $status
 * @property int $position
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @property string|null $owner_type
 * @property int|null $owner_id
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Activitylog\Models\Activity> $activitiesAsSubject
 * @property-read int|null $activities_as_subject_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Issue> $issues
 * @property-read int|null $issues_count
 * @property-read \Illuminate\Database\Eloquent\Model|null $owner
 * @method static \Database\Factories\BoardColumnFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BoardColumn newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BoardColumn newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BoardColumn query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BoardColumn whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BoardColumn whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BoardColumn whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BoardColumn whereOwnerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BoardColumn whereOwnerType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BoardColumn wherePosition($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BoardColumn whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BoardColumn whereUpdatedAt($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperBoardColumn {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $organization_id
 * @property string $name
 * @property string $slug
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @property numeric|null $billing_rate
 * @property int|null $billing_contact_id
 * @property int|null $billed_through_client_id
 * @property \App\Enums\BillingCycle|null $billing_cycle
 * @property \Carbon\CarbonImmutable|null $billing_cycle_starts_on
 * @property array<array-key, mixed>|null $billing_cc_emails
 * @property int|null $payment_terms_days
 * @property \Carbon\CarbonImmutable|null $billing_opened_through
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Activitylog\Models\Activity> $activitiesAsSubject
 * @property-read int|null $activities_as_subject_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Client> $billedClients
 * @property-read int|null $billed_clients_count
 * @property-read Client|null $billedThrough
 * @property-read \App\Models\ClientContact|null $billingContact
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ClientContact> $contacts
 * @property-read int|null $contacts_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ClientDomain> $domains
 * @property-read int|null $domains_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Invoice> $invoices
 * @property-read int|null $invoices_count
 * @property-read \App\Models\Organization|null $organization
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Project> $projects
 * @property-read int|null $projects_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Ticket> $tickets
 * @property-read int|null $tickets_count
 * @method static \Database\Factories\ClientFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Client newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Client newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Client query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Client whereBilledThroughClientId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Client whereBillingCcEmails($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Client whereBillingContactId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Client whereBillingCycle($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Client whereBillingCycleStartsOn($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Client whereBillingOpenedThrough($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Client whereBillingRate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Client whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Client whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Client whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Client whereOrganizationId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Client wherePaymentTermsDays($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Client whereSlug($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Client whereUpdatedAt($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperClient {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $organization_id
 * @property int $client_id
 * @property string|null $name
 * @property string $email
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Activitylog\Models\Activity> $activitiesAsSubject
 * @property-read int|null $activities_as_subject_count
 * @property-read \App\Models\Client $client
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Ticket> $tickets
 * @property-read int|null $tickets_count
 * @method static \Database\Factories\ClientContactFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClientContact matching(string $term)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClientContact newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClientContact newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClientContact query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClientContact whereClientId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClientContact whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClientContact whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClientContact whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClientContact whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClientContact whereOrganizationId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClientContact whereUpdatedAt($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperClientContact {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $organization_id
 * @property int $client_id
 * @property string $domain
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @property-read \App\Models\Client $client
 * @method static \Database\Factories\ClientDomainFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClientDomain newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClientDomain newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClientDomain query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClientDomain whereClientId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClientDomain whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClientDomain whereDomain($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClientDomain whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClientDomain whereOrganizationId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClientDomain whereUpdatedAt($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperClientDomain {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $commentable_type
 * @property int $commentable_id
 * @property int|null $parent_id
 * @property int|null $user_id
 * @property string $body
 * @property bool $is_internal
 * @property string $body_text
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Activitylog\Models\Activity> $activitiesAsSubject
 * @property-read int|null $activities_as_subject_count
 * @property-read \App\Models\User|null $author
 * @property-read \Illuminate\Database\Eloquent\Model $commentable
 * @property-read \App\Models\EmailMessage|null $emailMessage
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\User> $mentions
 * @property-read int|null $mentions_count
 * @property-read Comment|null $parent
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Comment> $replies
 * @property-read int|null $replies_count
 * @method static \Database\Factories\CommentFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Comment newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Comment newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Comment public()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Comment query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Comment threadsVisibleTo(bool $isMember, bool $newestFirst = false)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Comment topLevel()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Comment whereBody($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Comment whereBodyText($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Comment whereCommentableId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Comment whereCommentableType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Comment whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Comment whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Comment whereIsInternal($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Comment whereParentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Comment whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Comment whereUserId($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperComment {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $user_id
 * @property string $email
 * @property \Carbon\CarbonImmutable|null $verified_at
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Activitylog\Models\Activity> $activitiesAsSubject
 * @property-read int|null $activities_as_subject_count
 * @property-read \App\Models\User $user
 * @method static \Database\Factories\EmailAddressFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailAddress newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailAddress newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailAddress query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailAddress verified()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailAddress whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailAddress whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailAddress whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailAddress whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailAddress whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailAddress whereVerifiedAt($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperEmailAddress {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $email_message_id
 * @property string $filename
 * @property string $content_type
 * @property int $size
 * @property string $path
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @property-read \App\Models\EmailMessage $emailMessage
 * @method static \Database\Factories\EmailAttachmentFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailAttachment newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailAttachment newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailAttachment query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailAttachment whereContentType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailAttachment whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailAttachment whereEmailMessageId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailAttachment whereFilename($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailAttachment whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailAttachment wherePath($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailAttachment whereSize($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailAttachment whereUpdatedAt($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperEmailAttachment {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $comment_id
 * @property \App\Enums\EmailDirection $direction
 * @property \App\Enums\EmailDeliveryStatus $delivery_status
 * @property string $from_email
 * @property string|null $from_name
 * @property array<array-key, mixed> $to
 * @property array<array-key, mixed> $cc
 * @property string $message_id
 * @property string|null $in_reply_to
 * @property string|null $references
 * @property string|null $resend_id
 * @property string|null $body_html
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\EmailAttachment> $attachments
 * @property-read int|null $attachments_count
 * @property-read \App\Models\Comment $comment
 * @method static \Database\Factories\EmailMessageFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailMessage newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailMessage newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailMessage query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailMessage whereBodyHtml($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailMessage whereCc($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailMessage whereCommentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailMessage whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailMessage whereDeliveryStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailMessage whereDirection($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailMessage whereFromEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailMessage whereFromName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailMessage whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailMessage whereInReplyTo($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailMessage whereMessageId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailMessage whereReferences($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailMessage whereResendId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailMessage whereTo($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailMessage whereUpdatedAt($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperEmailMessage {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $project_id
 * @property int|null $user_id
 * @property string $title
 * @property string|null $description
 * @property \App\Enums\FeedbackType $type
 * @property \App\Enums\FeedbackStatus $status
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Activitylog\Models\Activity> $activitiesAsSubject
 * @property-read int|null $activities_as_subject_count
 * @property-read \App\Models\User|null $author
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Comment> $comments
 * @property-read int|null $comments_count
 * @property-read \App\Models\Issue|null $issue
 * @property-read \App\Models\Project $project
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Vote> $votes
 * @property-read int|null $votes_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Feedback active()
 * @method static \Database\Factories\FeedbackFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Feedback newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Feedback newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Feedback query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Feedback whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Feedback whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Feedback whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Feedback whereProjectId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Feedback whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Feedback whereTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Feedback whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Feedback whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Feedback whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Feedback withVoteSummary(?\App\Models\User $viewer)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperFeedback {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $organization_id
 * @property int $client_id
 * @property int|null $project_id
 * @property int|null $sprint_id
 * @property string $number
 * @property string $public_token
 * @property \App\Enums\InvoiceStatus $status
 * @property int|null $contact_id
 * @property array<array-key, mixed>|null $cc_emails
 * @property \Carbon\CarbonImmutable|null $issue_date
 * @property \Carbon\CarbonImmutable|null $due_date
 * @property \Carbon\CarbonImmutable|null $service_period_start
 * @property \Carbon\CarbonImmutable|null $service_period_end
 * @property numeric $subtotal
 * @property numeric|null $tax_rate
 * @property numeric $tax_amount
 * @property \App\Enums\AdjustmentType|null $discount_type
 * @property numeric|null $discount_value
 * @property numeric $discount_amount
 * @property \App\Enums\AdjustmentType|null $deposit_type
 * @property numeric|null $deposit_value
 * @property numeric $deposit_amount
 * @property numeric $total
 * @property numeric $amount_paid
 * @property string|null $notes
 * @property string|null $terms
 * @property \Carbon\CarbonImmutable|null $sent_at
 * @property \Carbon\CarbonImmutable|null $voided_at
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @property \Carbon\CarbonImmutable|null $closes_at
 * @property \Carbon\CarbonImmutable|null $reminded_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Activitylog\Models\Activity> $activitiesAsSubject
 * @property-read int|null $activities_as_subject_count
 * @property-read \App\Models\Client $client
 * @property-read \App\Models\ClientContact|null $contact
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\InvoiceLine> $lines
 * @property-read int|null $lines_count
 * @property-read \App\Models\Organization|null $organization
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\InvoicePayment> $payments
 * @property-read int|null $payments_count
 * @property-read \App\Models\Project|null $project
 * @property-read \App\Models\Sprint|null $sprint
 * @method static \Database\Factories\InvoiceFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereAmountPaid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereCcEmails($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereClientId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereClosesAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereContactId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereDepositAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereDepositType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereDepositValue($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereDiscountAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereDiscountType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereDiscountValue($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereDueDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereIssueDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereOrganizationId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereProjectId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice wherePublicToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereRemindedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereSentAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereServicePeriodEnd($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereServicePeriodStart($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereSprintId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereSubtotal($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereTaxAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereTaxRate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereTerms($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereTotal($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereVoidedAt($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperInvoice {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $invoice_id
 * @property string $description
 * @property numeric $quantity
 * @property \App\Enums\LineUnit $unit
 * @property numeric $rate
 * @property numeric $amount
 * @property int|null $work_type_id
 * @property int|null $issue_id
 * @property string|null $reference_url
 * @property int $sort_order
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Activitylog\Models\Activity> $activitiesAsSubject
 * @property-read int|null $activities_as_subject_count
 * @property-read \App\Models\Invoice $invoice
 * @property-read \App\Models\Issue|null $issue
 * @property-read \App\Models\WorkType|null $workType
 * @method static \Database\Factories\InvoiceLineFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceLine newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceLine newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceLine query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceLine whereAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceLine whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceLine whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceLine whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceLine whereInvoiceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceLine whereIssueId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceLine whereQuantity($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceLine whereRate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceLine whereReferenceUrl($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceLine whereSortOrder($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceLine whereUnit($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceLine whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceLine whereWorkTypeId($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperInvoiceLine {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $invoice_id
 * @property numeric $amount
 * @property \Carbon\CarbonImmutable $paid_on
 * @property \App\Enums\PaymentMethod $method
 * @property string|null $reference
 * @property string|null $notes
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Activitylog\Models\Activity> $activitiesAsSubject
 * @property-read int|null $activities_as_subject_count
 * @property-read \App\Models\Invoice $invoice
 * @method static \Database\Factories\InvoicePaymentFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoicePayment newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoicePayment newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoicePayment query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoicePayment whereAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoicePayment whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoicePayment whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoicePayment whereInvoiceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoicePayment whereMethod($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoicePayment whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoicePayment wherePaidOn($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoicePayment whereReference($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoicePayment whereUpdatedAt($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperInvoicePayment {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $project_id
 * @property int|null $team_id
 * @property int|null $sprint_id
 * @property int|null $board_column_id
 * @property int|null $feedback_id
 * @property int|null $assignee_id
 * @property int|null $created_by
 * @property int $number
 * @property string $title
 * @property string|null $description
 * @property \App\Enums\IssueStatus $status
 * @property \App\Enums\IssuePriority $priority
 * @property int|null $points
 * @property int $position
 * @property \Carbon\CarbonImmutable|null $completed_at
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Activitylog\Models\Activity> $activitiesAsSubject
 * @property-read int|null $activities_as_subject_count
 * @property-read \App\Models\User|null $assignee
 * @property-read \App\Models\BoardColumn|null $boardColumn
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Comment> $comments
 * @property-read int|null $comments_count
 * @property-read \App\Models\User|null $creator
 * @property-read \App\Models\Feedback|null $feedback
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\IssueLink> $inverseLinks
 * @property-read int|null $inverse_links_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\IssueLink> $links
 * @property-read int|null $links_count
 * @property-read \App\Models\Project $project
 * @property-read \App\Models\Sprint|null $sprint
 * @property-read \App\Models\Team|null $team
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Ticket> $tickets
 * @property-read int|null $tickets_count
 * @method static \Database\Factories\IssueFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Issue newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Issue newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Issue onOrganizationBoard(\Illuminate\Support\Collection $projectIds, \App\Models\User $user, ?\App\Models\Team $team, array $filters)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Issue ownedBy(?\App\Models\Team $team)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Issue query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Issue whereAssigneeId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Issue whereBoardColumnId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Issue whereCompletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Issue whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Issue whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Issue whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Issue whereFeedbackId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Issue whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Issue whereNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Issue wherePoints($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Issue wherePosition($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Issue wherePriority($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Issue whereProjectId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Issue whereSprintId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Issue whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Issue whereTeamId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Issue whereTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Issue whereUpdatedAt($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperIssue {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $issue_id
 * @property int $related_issue_id
 * @property \App\Enums\IssueLinkType $type
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @property-read \App\Models\Issue $issue
 * @property-read \App\Models\Issue $relatedIssue
 * @method static \Database\Factories\IssueLinkFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IssueLink newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IssueLink newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IssueLink query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IssueLink whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IssueLink whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IssueLink whereIssueId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IssueLink whereRelatedIssueId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IssueLink whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IssueLink whereUpdatedAt($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperIssueLink {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $logo_path
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @property \Carbon\CarbonImmutable|null $deleted_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Activitylog\Models\Activity> $activitiesAsSubject
 * @property-read int|null $activities_as_subject_count
 * @property-read \App\Models\BillingSetting|null $billingSettingsRecord
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\OrganizationModuleSetting> $capabilitySettings
 * @property-read int|null $capability_settings_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ClientContact> $clientContacts
 * @property-read int|null $client_contacts_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Client> $clients
 * @property-read int|null $clients_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\BoardColumn> $columns
 * @property-read int|null $columns_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\OrganizationInvitation> $invitations
 * @property-read int|null $invitations_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Invoice> $invoices
 * @property-read int|null $invoices_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Issue> $issues
 * @property-read int|null $issues_count
 * @property-read \App\Models\OrganizationMembership|null $pivot
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\User> $members
 * @property-read int|null $members_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\OrganizationMembership> $memberships
 * @property-read int|null $memberships_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Project> $projects
 * @property-read int|null $projects_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Permission\Models\Role> $roles
 * @property-read int|null $roles_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Permission\Models\Role> $rolesWithMembers
 * @property-read int|null $roles_with_members_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Sprint> $sprints
 * @property-read int|null $sprints_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Team> $teams
 * @property-read int|null $teams_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Ticket> $tickets
 * @property-read int|null $tickets_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\WorkType> $workTypes
 * @property-read int|null $work_types_count
 * @method static \Database\Factories\OrganizationFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Organization newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Organization newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Organization onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Organization query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Organization whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Organization whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Organization whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Organization whereLogoPath($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Organization whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Organization whereSlug($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Organization whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Organization withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Organization withoutTrashed()
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperOrganization {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $code
 * @property int $organization_id
 * @property string $email
 * @property int $role_id
 * @property int $invited_by
 * @property \Carbon\CarbonImmutable|null $expires_at
 * @property \Carbon\CarbonImmutable|null $accepted_at
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Activitylog\Models\Activity> $activitiesAsSubject
 * @property-read int|null $activities_as_subject_count
 * @property-read \App\Models\User $inviter
 * @property-read \App\Models\Organization|null $organization
 * @property-read \Spatie\Permission\Models\Role $role
 * @method static \Database\Factories\OrganizationInvitationFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrganizationInvitation newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrganizationInvitation newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrganizationInvitation pendingFor(\App\Models\User $user)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrganizationInvitation query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrganizationInvitation whereAcceptedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrganizationInvitation whereCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrganizationInvitation whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrganizationInvitation whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrganizationInvitation whereExpiresAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrganizationInvitation whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrganizationInvitation whereInvitedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrganizationInvitation whereOrganizationId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrganizationInvitation whereRoleId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrganizationInvitation whereUpdatedAt($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperOrganizationInvitation {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $organization_id
 * @property int $user_id
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Activitylog\Models\Activity> $activitiesAsSubject
 * @property-read int|null $activities_as_subject_count
 * @property-read \App\Models\Organization|null $organization
 * @property-read \App\Models\User $user
 * @method static \Database\Factories\OrganizationMembershipFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrganizationMembership newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrganizationMembership newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrganizationMembership query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrganizationMembership whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrganizationMembership whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrganizationMembership whereOrganizationId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrganizationMembership whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrganizationMembership whereUserId($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperOrganizationMembership {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $organization_id
 * @property string $module
 * @property string $capability
 * @property bool $enabled
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @property-read \App\Models\Organization|null $organization
 * @method static \Database\Factories\OrganizationModuleSettingFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrganizationModuleSetting newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrganizationModuleSetting newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrganizationModuleSetting query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrganizationModuleSetting whereCapability($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrganizationModuleSetting whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrganizationModuleSetting whereEnabled($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrganizationModuleSetting whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrganizationModuleSetting whereModule($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrganizationModuleSetting whereOrganizationId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrganizationModuleSetting whereUpdatedAt($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperOrganizationModuleSetting {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $organization_id
 * @property int|null $client_id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property \App\Enums\ProjectVisibility $visibility
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @property numeric|null $billing_rate
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Activitylog\Models\Activity> $activitiesAsSubject
 * @property-read int|null $activities_as_subject_count
 * @property-read \App\Models\Client|null $client
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Feedback> $feedback
 * @property-read int|null $feedback_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Issue> $issues
 * @property-read int|null $issues_count
 * @property-read \App\Models\Organization|null $organization
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Team> $teams
 * @property-read int|null $teams_count
 * @method static \Database\Factories\ProjectFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Project newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Project newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Project query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Project visibleTo(?\App\Models\User $user)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Project whereBillingRate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Project whereClientId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Project whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Project whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Project whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Project whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Project whereOrganizationId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Project whereSlug($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Project whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Project whereVisibility($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperProject {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $name
 * @property \Carbon\CarbonImmutable $starts_at
 * @property \Carbon\CarbonImmutable $ends_at
 * @property \App\Enums\SprintStatus $status
 * @property \Carbon\CarbonImmutable|null $completed_at
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @property string|null $owner_type
 * @property int|null $owner_id
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Activitylog\Models\Activity> $activitiesAsSubject
 * @property-read int|null $activities_as_subject_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Issue> $issues
 * @property-read int|null $issues_count
 * @property-read \Illuminate\Database\Eloquent\Model|null $owner
 * @method static \Database\Factories\SprintFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Sprint newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Sprint newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Sprint onBoardFor(\App\Models\Organization $organization, ?\App\Models\Team $team)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Sprint query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Sprint whereCompletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Sprint whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Sprint whereEndsAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Sprint whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Sprint whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Sprint whereOwnerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Sprint whereOwnerType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Sprint whereStartsAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Sprint whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Sprint whereUpdatedAt($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperSprint {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $organization_id
 * @property string $name
 * @property string $slug
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Activitylog\Models\Activity> $activitiesAsSubject
 * @property-read int|null $activities_as_subject_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\TeamModuleSetting> $capabilitySettings
 * @property-read int|null $capability_settings_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\BoardColumn> $columns
 * @property-read int|null $columns_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Issue> $issues
 * @property-read int|null $issues_count
 * @property-read \App\Models\TeamMembership|null $pivot
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\User> $members
 * @property-read int|null $members_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\TeamMembership> $memberships
 * @property-read int|null $memberships_count
 * @property-read \App\Models\Organization|null $organization
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Project> $projects
 * @property-read int|null $projects_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Sprint> $sprints
 * @property-read int|null $sprints_count
 * @method static \Database\Factories\TeamFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Team newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Team newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Team query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Team whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Team whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Team whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Team whereOrganizationId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Team whereSlug($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Team whereUpdatedAt($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperTeam {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $team_id
 * @property int $user_id
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Activitylog\Models\Activity> $activitiesAsSubject
 * @property-read int|null $activities_as_subject_count
 * @property-read \App\Models\Team $team
 * @property-read \App\Models\User $user
 * @method static \Database\Factories\TeamMembershipFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TeamMembership newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TeamMembership newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TeamMembership query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TeamMembership whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TeamMembership whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TeamMembership whereTeamId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TeamMembership whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TeamMembership whereUserId($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperTeamMembership {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $team_id
 * @property string $module
 * @property string $capability
 * @property bool $enabled
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @property-read \App\Models\Team $team
 * @method static \Database\Factories\TeamModuleSettingFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TeamModuleSetting newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TeamModuleSetting newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TeamModuleSetting query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TeamModuleSetting whereCapability($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TeamModuleSetting whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TeamModuleSetting whereEnabled($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TeamModuleSetting whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TeamModuleSetting whereModule($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TeamModuleSetting whereTeamId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TeamModuleSetting whereUpdatedAt($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperTeamModuleSetting {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $organization_id
 * @property int|null $client_id
 * @property int|null $client_contact_id
 * @property int $number
 * @property string $subject
 * @property \App\Enums\TicketStatus $status
 * @property string|null $requester_name
 * @property string $requester_email
 * @property int|null $requester_user_id
 * @property int|null $issue_id
 * @property int|null $reporter_id
 * @property int|null $created_by
 * @property \Carbon\CarbonImmutable|null $issue_resolved_at
 * @property \Carbon\CarbonImmutable|null $last_message_at
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Activitylog\Models\Activity> $activitiesAsSubject
 * @property-read int|null $activities_as_subject_count
 * @property-read \App\Models\Client|null $client
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Comment> $comments
 * @property-read int|null $comments_count
 * @property-read \App\Models\ClientContact|null $contact
 * @property-read \App\Models\User|null $creator
 * @property-read \App\Models\Issue|null $issue
 * @property-read \App\Models\Organization|null $organization
 * @property-read \App\Models\User|null $reporter
 * @property-read \App\Models\User|null $requester
 * @method static \Database\Factories\TicketFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Ticket inInbox(\App\Models\User $viewer, array $filters)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Ticket newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Ticket newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Ticket query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Ticket whereClientContactId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Ticket whereClientId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Ticket whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Ticket whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Ticket whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Ticket whereIssueId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Ticket whereIssueResolvedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Ticket whereLastMessageAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Ticket whereNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Ticket whereOrganizationId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Ticket whereReporterId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Ticket whereRequesterEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Ticket whereRequesterName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Ticket whereRequesterUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Ticket whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Ticket whereSubject($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Ticket whereUpdatedAt($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperTicket {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property \Carbon\CarbonImmutable|null $email_verified_at
 * @property string|null $timezone
 * @property string $password
 * @property int|null $current_organization_id
 * @property int|null $current_team_id
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property \Carbon\CarbonImmutable|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Activitylog\Models\Activity> $activitiesAsSubject
 * @property-read int|null $activities_as_subject_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Issue> $assignedIssues
 * @property-read int|null $assigned_issues_count
 * @property-read \App\Models\Organization|null $currentOrganization
 * @property-read \App\Models\Team|null $currentTeam
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\EmailAddress> $emailAddresses
 * @property-read int|null $email_addresses_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Feedback> $feedback
 * @property-read int|null $feedback_count
 * @property-read \Illuminate\Notifications\DatabaseNotificationCollection<int, \Illuminate\Notifications\DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\OrganizationMembership> $organizationMemberships
 * @property-read int|null $organization_memberships_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Permission\Models\Role> $organizationRoles
 * @property-read int|null $organization_roles_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Organization> $organizations
 * @property-read int|null $organizations_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Organization> $ownedOrganizations
 * @property-read int|null $owned_organizations_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Laravel\Passkeys\Passkey> $passkeys
 * @property-read int|null $passkeys_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Permission\Models\Permission> $permissions
 * @property-read int|null $permissions_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Permission\Models\Role> $roles
 * @property-read int|null $roles_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Team> $teams
 * @property-read int|null $teams_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Vote> $votes
 * @property-read int|null $votes_count
 * @method static \Database\Factories\UserFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User permission($permissions, bool $without = false)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User role($roles, ?string $guard = null, bool $without = false)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User team($teams, bool $without = false)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereCurrentOrganizationId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereCurrentTeamId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmailVerifiedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User wherePassword($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereRememberToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereTimezone($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereTwoFactorConfirmedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereTwoFactorRecoveryCodes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereTwoFactorSecret($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User withoutPermission($permissions)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User withoutRole($roles, ?string $guard = null)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User withoutTeam($teams)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperUser {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $feedback_id
 * @property int $user_id
 * @property int $direction
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @property-read \App\Models\Feedback $feedback
 * @property-read \App\Models\User $user
 * @method static \Database\Factories\VoteFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Vote newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Vote newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Vote query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Vote whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Vote whereDirection($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Vote whereFeedbackId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Vote whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Vote whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Vote whereUserId($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperVote {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $organization_id
 * @property string $name
 * @property string $color
 * @property bool $is_active
 * @property int $sort_order
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Activitylog\Models\Activity> $activitiesAsSubject
 * @property-read int|null $activities_as_subject_count
 * @property-read \App\Models\Organization|null $organization
 * @method static \Database\Factories\WorkTypeFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WorkType newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WorkType newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WorkType query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WorkType whereColor($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WorkType whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WorkType whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WorkType whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WorkType whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WorkType whereOrganizationId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WorkType whereSortOrder($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WorkType whereUpdatedAt($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperWorkType {}
}

