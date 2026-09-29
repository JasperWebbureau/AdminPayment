<?php

declare(strict_types=1);

namespace Flexgrid\Modules\AdminPayment\Entity;

use Repository\RepositoryEntity;

/**
 * @FG\Entity[name=admin_payment_allocation,repository=Flexgrid\Modules\AdminPayment\Repository\PaymentAllocationRecordRepository,type=Module,in_menu=false]
 * @FG\Index::tenant_public[columns={tenantId,publicId},unique=true]
 * @FG\Index::tenant_payment_target[columns={tenantId,paymentId,targetType,targetPublicId},unique=true]
 * @FG\Index::tenant_target[columns={tenantId,targetType,targetPublicId}]
 * @FG\Index::tenant_payment[columns={tenantId,paymentId}]
 */
final class PaymentAllocationRecord extends RepositoryEntity
{
    /** @FG\Column[type=primary] */ protected $id;
    /** @FG\Column[type=varchar,length=64,required=true] */ protected $tenantId;
    /** @FG\Column[type=varchar,length=64,required=true] */ protected $publicId;
    /** @FG\Column[type=int,required=true] */ protected $paymentId;
    /** @FG\Column[type=varchar,length=32,required=true] */ protected $targetType;
    /** @FG\Column[type=varchar,length=64,required=true] */ protected $targetPublicId;
    /** @FG\Column[type=bigint,required=true] */ protected $amountMinor;
    /** @FG\Column[type=bigint,required=true] */ protected $allocatedAt;
    /** @FG\Column[type=bigint,required=true] */ protected $createdAt;


    // --- Auto-generated getters and setters ---

    /**
     * Get the value of id
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set the value of id
     *
     * @param mixed $value
     * @return $this
     */
    public function setId($value)
    {
        $this->id = $value;
        return $this;
    }

    /**
     * Get the value of tenantId
     */
    public function getTenantId()
    {
        return $this->tenantId;
    }

    /**
     * Set the value of tenantId
     *
     * @param mixed $value
     * @return $this
     */
    public function setTenantId($value)
    {
        $this->tenantId = $value;
        return $this;
    }

    /**
     * Get the value of publicId
     */
    public function getPublicId()
    {
        return $this->publicId;
    }

    /**
     * Set the value of publicId
     *
     * @param mixed $value
     * @return $this
     */
    public function setPublicId($value)
    {
        $this->publicId = $value;
        return $this;
    }

    /**
     * Get the value of paymentId
     */
    public function getPaymentId()
    {
        return $this->paymentId;
    }

    /**
     * Set the value of paymentId
     *
     * @param mixed $value
     * @return $this
     */
    public function setPaymentId($value)
    {
        $this->paymentId = $value;
        return $this;
    }

    /**
     * Get the value of targetType
     */
    public function getTargetType()
    {
        return $this->targetType;
    }

    /**
     * Set the value of targetType
     *
     * @param mixed $value
     * @return $this
     */
    public function setTargetType($value)
    {
        $this->targetType = $value;
        return $this;
    }

    /**
     * Get the value of targetPublicId
     */
    public function getTargetPublicId()
    {
        return $this->targetPublicId;
    }

    /**
     * Set the value of targetPublicId
     *
     * @param mixed $value
     * @return $this
     */
    public function setTargetPublicId($value)
    {
        $this->targetPublicId = $value;
        return $this;
    }

    /**
     * Get the value of amountMinor
     */
    public function getAmountMinor()
    {
        return $this->amountMinor;
    }

    /**
     * Set the value of amountMinor
     *
     * @param mixed $value
     * @return $this
     */
    public function setAmountMinor($value)
    {
        $this->amountMinor = $value;
        return $this;
    }

    /**
     * Get the value of allocatedAt
     */
    public function getAllocatedAt()
    {
        return $this->allocatedAt;
    }

    /**
     * Set the value of allocatedAt
     *
     * @param mixed $value
     * @return $this
     */
    public function setAllocatedAt($value)
    {
        $this->allocatedAt = $value;
        return $this;
    }

    /**
     * Get the value of createdAt
     */
    public function getCreatedAt()
    {
        return $this->createdAt;
    }

    /**
     * Set the value of createdAt
     *
     * @param mixed $value
     * @return $this
     */
    public function setCreatedAt($value)
    {
        $this->createdAt = $value;
        return $this;
    }

    /**
     * Get the value of makeTime
     */
    public function getMakeTime()
    {
        return $this->makeTime;
    }

    /**
     * Set the value of makeTime
     *
     * @param mixed $value
     * @return $this
     */
    public function setMakeTime($value)
    {
        $this->makeTime = $value;
        return $this;
    }

}
