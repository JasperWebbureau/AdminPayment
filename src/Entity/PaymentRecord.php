<?php

declare(strict_types=1);

namespace Flexgrid\Modules\AdminPayment\Entity;

use Repository\RepositoryEntity;

/**
 * Autowire-opslagrecord. Domeinregels leven in Domain\Model\Payment.
 *
 * @FG\Entity[name=admin_payment,repository=Flexgrid\Modules\AdminPayment\Repository\PaymentRecordRepository,type=Module,in_menu=false]
 * @FG\Index::tenant_public[columns={tenantId,publicId},unique=true]
 * @FG\Index::tenant_source_external[columns={tenantId,source,externalId},unique=true]
 * @FG\Index::tenant_booked_on[columns={tenantId,bookedOn}]
 * @FG\Index::tenant_reference[columns={tenantId,reference}]
 */
final class PaymentRecord extends RepositoryEntity
{
    /** @FG\Column[type=primary] */ protected $id;
    /** @FG\Column[type=varchar,length=64,required=true] */ protected $tenantId;
    /** @FG\Column[type=varchar,length=64,required=true] */ protected $publicId;
    /** @FG\Column[type=varchar,length=16,required=true] */ protected $paymentType;
    /** @FG\Column[type=bigint,required=true] */ protected $amountMinor;
    /** @FG\Column[type=varchar,length=3,required=true] */ protected $currency;
    /** @FG\Column[type=varchar,length=10,required=true] */ protected $bookedOn;
    /** @FG\Column[type=varchar,length=255] */ protected $description;
    /** @FG\Column[type=varchar,length=255] */ protected $reference;
    /** @FG\Column[type=varchar,length=64,required=true] */ protected $source;
    /** @FG\Column[type=varchar,length=128] */ protected $externalId;
    /** @FG\Column[type=bigint,required=true] */ protected $createdAt;
    /** @FG\Column[type=bigint,required=true] */ protected $updatedAt;


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
     * Get the value of paymentType
     */
    public function getPaymentType()
    {
        return $this->paymentType;
    }

    /**
     * Set the value of paymentType
     *
     * @param mixed $value
     * @return $this
     */
    public function setPaymentType($value)
    {
        $this->paymentType = $value;
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
     * Get the value of currency
     */
    public function getCurrency()
    {
        return $this->currency;
    }

    /**
     * Set the value of currency
     *
     * @param mixed $value
     * @return $this
     */
    public function setCurrency($value)
    {
        $this->currency = $value;
        return $this;
    }

    /**
     * Get the value of bookedOn
     */
    public function getBookedOn()
    {
        return $this->bookedOn;
    }

    /**
     * Set the value of bookedOn
     *
     * @param mixed $value
     * @return $this
     */
    public function setBookedOn($value)
    {
        $this->bookedOn = $value;
        return $this;
    }

    /**
     * Get the value of description
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * Set the value of description
     *
     * @param mixed $value
     * @return $this
     */
    public function setDescription($value)
    {
        $this->description = $value;
        return $this;
    }

    /**
     * Get the value of reference
     */
    public function getReference()
    {
        return $this->reference;
    }

    /**
     * Set the value of reference
     *
     * @param mixed $value
     * @return $this
     */
    public function setReference($value)
    {
        $this->reference = $value;
        return $this;
    }

    /**
     * Get the value of source
     */
    public function getSource()
    {
        return $this->source;
    }

    /**
     * Set the value of source
     *
     * @param mixed $value
     * @return $this
     */
    public function setSource($value)
    {
        $this->source = $value;
        return $this;
    }

    /**
     * Get the value of externalId
     */
    public function getExternalId()
    {
        return $this->externalId;
    }

    /**
     * Set the value of externalId
     *
     * @param mixed $value
     * @return $this
     */
    public function setExternalId($value)
    {
        $this->externalId = $value;
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
     * Get the value of updatedAt
     */
    public function getUpdatedAt()
    {
        return $this->updatedAt;
    }

    /**
     * Set the value of updatedAt
     *
     * @param mixed $value
     * @return $this
     */
    public function setUpdatedAt($value)
    {
        $this->updatedAt = $value;
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
