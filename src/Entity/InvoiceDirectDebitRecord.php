<?php

namespace Flexgrid\Modules\AdminPayment\Entity;

use Repository\RepositoryEntity;

/**
 * @FG\Entity[name=admin_invoice_direct_debit,repository=Flexgrid\Modules\AdminPayment\Repository\InvoiceDirectDebitRecordRepository,type=Module,in_menu=false,hide=true]
 * @FG\Index::tenant_invoice[columns={tenantId,invoicePublicId},unique=true]
 * @FG\Index::provider_payment[columns={paymentId},unique=true]
 */
final class InvoiceDirectDebitRecord extends RepositoryEntity
{
    /** @FG\Column[type=primary] */ protected $id;
    /** @FG\Column[type=varchar,length=64,required=true] */ protected $tenantId;
    /** @FG\Column[type=varchar,length=64,required=true] */ protected $invoicePublicId;
    /** @FG\Column[type=varchar,length=128,required=true] */ protected $invoiceNumber;
    /** @FG\Column[type=varchar,length=255,required=true] */ protected $customerName;
    /** @FG\Column[type=varchar,length=64,required=true] */ protected $customerPublicId;
    /** @FG\Column[type=varchar,length=128,required=true] */ protected $providerCustomerId;
    /** @FG\Column[type=varchar,length=128,required=true] */ protected $mandateId;
    /** @FG\Column[type=varchar,length=128,required=true] */ protected $profileId;
    /** @FG\Column[type=varchar,length=16,required=true] */ protected $mode;
    /** @FG\Column[type=varchar,length=128] */ protected $paymentId;
    /** @FG\Column[type=varchar,length=32,required=true] */ protected $status;
    /** @FG\Column[type=bigint,required=true] */ protected $amountMinor;
    /** @FG\Column[type=varchar,length=3,required=true] */ protected $currency;
    /** @FG\Column[type=varchar,length=10,required=true] */ protected $collectOn;
    /** @FG\Column[type=text,required=true] */ protected $notice;
    /** @FG\Column[type=bigint] */ protected $noticeSentAt;
    /** @FG\Column[type=text] */ protected $error;
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
     * Get the value of invoicePublicId
     */
    public function getInvoicePublicId()
    {
        return $this->invoicePublicId;
    }

    /**
     * Set the value of invoicePublicId
     *
     * @param mixed $value
     * @return $this
     */
    public function setInvoicePublicId($value)
    {
        $this->invoicePublicId = $value;
        return $this;
    }

    /**
     * Get the value of invoiceNumber
     */
    public function getInvoiceNumber()
    {
        return $this->invoiceNumber;
    }

    /**
     * Set the value of invoiceNumber
     *
     * @param mixed $value
     * @return $this
     */
    public function setInvoiceNumber($value)
    {
        $this->invoiceNumber = $value;
        return $this;
    }

    /**
     * Get the value of customerName
     */
    public function getCustomerName()
    {
        return $this->customerName;
    }

    /**
     * Set the value of customerName
     *
     * @param mixed $value
     * @return $this
     */
    public function setCustomerName($value)
    {
        $this->customerName = $value;
        return $this;
    }

    /**
     * Get the value of customerPublicId
     */
    public function getCustomerPublicId()
    {
        return $this->customerPublicId;
    }

    /**
     * Set the value of customerPublicId
     *
     * @param mixed $value
     * @return $this
     */
    public function setCustomerPublicId($value)
    {
        $this->customerPublicId = $value;
        return $this;
    }

    /**
     * Get the value of providerCustomerId
     */
    public function getProviderCustomerId()
    {
        return $this->providerCustomerId;
    }

    /**
     * Set the value of providerCustomerId
     *
     * @param mixed $value
     * @return $this
     */
    public function setProviderCustomerId($value)
    {
        $this->providerCustomerId = $value;
        return $this;
    }

    /**
     * Get the value of mandateId
     */
    public function getMandateId()
    {
        return $this->mandateId;
    }

    /**
     * Set the value of mandateId
     *
     * @param mixed $value
     * @return $this
     */
    public function setMandateId($value)
    {
        $this->mandateId = $value;
        return $this;
    }

    /**
     * Get the value of profileId
     */
    public function getProfileId()
    {
        return $this->profileId;
    }

    /**
     * Set the value of profileId
     *
     * @param mixed $value
     * @return $this
     */
    public function setProfileId($value)
    {
        $this->profileId = $value;
        return $this;
    }

    /**
     * Get the value of mode
     */
    public function getMode()
    {
        return $this->mode;
    }

    /**
     * Set the value of mode
     *
     * @param mixed $value
     * @return $this
     */
    public function setMode($value)
    {
        $this->mode = $value;
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
     * Get the value of status
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * Set the value of status
     *
     * @param mixed $value
     * @return $this
     */
    public function setStatus($value)
    {
        $this->status = $value;
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
     * Get the value of collectOn
     */
    public function getCollectOn()
    {
        return $this->collectOn;
    }

    /**
     * Set the value of collectOn
     *
     * @param mixed $value
     * @return $this
     */
    public function setCollectOn($value)
    {
        $this->collectOn = $value;
        return $this;
    }

    /**
     * Get the value of notice
     */
    public function getNotice()
    {
        return $this->notice;
    }

    /**
     * Set the value of notice
     *
     * @param mixed $value
     * @return $this
     */
    public function setNotice($value)
    {
        $this->notice = $value;
        return $this;
    }

    /**
     * Get the value of noticeSentAt
     */
    public function getNoticeSentAt()
    {
        return $this->noticeSentAt;
    }

    /**
     * Set the value of noticeSentAt
     *
     * @param mixed $value
     * @return $this
     */
    public function setNoticeSentAt($value)
    {
        $this->noticeSentAt = $value;
        return $this;
    }

    /**
     * Get the value of error
     */
    public function getError()
    {
        return $this->error;
    }

    /**
     * Set the value of error
     *
     * @param mixed $value
     * @return $this
     */
    public function setError($value)
    {
        $this->error = $value;
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
