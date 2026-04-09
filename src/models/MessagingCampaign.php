<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MessagingCampaign extends Model
{
    protected $table = 'messaging_campaigns';
    public $timestamps = true;

    const CREATED_AT = 'createdAt';
    const UPDATED_AT = 'updatedAt';

    protected $fillable = [
        'createdBy',
        'templateId',
        'subject',
        'body',
        'channels',
        'status',
        'recipientCount',
        'deliveredCount',
        'failedCount',
        'metadata',
    ];

    protected $casts = [
        'createdBy' => 'integer',
        'templateId' => 'integer',
        'channels' => 'array',
        'recipientCount' => 'integer',
        'deliveredCount' => 'integer',
        'failedCount' => 'integer',
        'metadata' => 'array',
        'createdAt' => 'datetime',
        'updatedAt' => 'datetime',
    ];

    public function recipients()
    {
        return $this->hasMany(MessagingCampaignRecipient::class, 'campaignId');
    }

    public function template()
    {
        return $this->belongsTo(MessagingTemplate::class, 'templateId');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'createdBy');
    }
}
