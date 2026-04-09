<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MessagingCampaignRecipient extends Model
{
    protected $table = 'messaging_campaign_recipients';
    public $timestamps = true;

    const CREATED_AT = 'createdAt';
    const UPDATED_AT = 'updatedAt';

    protected $fillable = [
        'campaignId',
        'customerId',
        'channel',
        'status',
        'error',
        'providerMessageId',
        'sentAt',
    ];

    protected $casts = [
        'campaignId' => 'integer',
        'customerId' => 'integer',
        'sentAt' => 'datetime',
        'createdAt' => 'datetime',
        'updatedAt' => 'datetime',
    ];

    public function campaign()
    {
        return $this->belongsTo(MessagingCampaign::class, 'campaignId');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customerId');
    }
}
