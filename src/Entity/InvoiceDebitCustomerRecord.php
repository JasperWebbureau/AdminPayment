<?php

namespace Flexgrid\Modules\AdminPayment\Entity;

use Repository\RepositoryEntity;

/**
 * @FG\Entity[name=admin_invoice_debit_customer,repository=Flexgrid\Modules\AdminPayment\Repository\InvoiceDebitCustomerRecordRepository,type=Module,in_menu=false,hide=true]
 * @FG\Index::tenant_customer[columns={tenantId,customerPublicId},unique=true]
 */
final class InvoiceDebitCustomerRecord extends RepositoryEntity
{
    /** @FG\Column[type=primary] */ protected $id;
    /** @FG\Column[type=varchar,length=64,required=true] */ protected $tenantId;
    /** @FG\Column[type=varchar,length=64,required=true] */ protected $customerPublicId;
    /** @FG\Column[type=varchar,length=128] */ protected $providerCustomerId;
    /** @FG\Column[type=varchar,length=128] */ protected $mandateId;
    /** @FG\Column[type=varchar,length=128] */ protected $setupPaymentId;
    /** @FG\Column[type=int,required=true] */ protected $setupAttempt;
    /** @FG\Column[type=varchar,length=2048] */ protected $setupUrl;
    /** @FG\Column[type=varchar,length=128,required=true] */ protected $profileId;
    /** @FG\Column[type=varchar,length=16,required=true] */ protected $mode;
    /** @FG\Column[type=varchar,length=32,required=true] */ protected $status;
    /** @FG\Column[type=tinyint,required=true] */ protected $enabled;
    /** @FG\Column[type=bigint] */ protected $consentAt;
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
     * Get the value of setupPaymentId
     */
    public function getSetupPaymentId()
    {
        return $this->setupPaymentId;
    }

    /**
     * Set the value of setupPaymentId
     *
     * @param mixed $value
     * @return $this
     */
    public function setSetupPaymentId($value)
    {
        $this->setupPaymentId = $value;
        return $this;
    }

    /**
     * Get the value of setupAttempt
     */
    public function getSetupAttempt()
    {
        return $this->setupAttempt;
    }

    /**
     * Set the value of setupAttempt
     *
     * @param mixed $value
     * @return $this
     */
    public function setSetupAttempt($value)
    {
        $this->setupAttempt = $value;
        return $this;
    }

    /**
     * Get the value of setupUrl
     */
    public function getSetupUrl()
    {
        return $this->setupUrl;
    }

    /**
     * Set the value of setupUrl
     *
     * @param mixed $value
     * @return $this
     */
    public function setSetupUrl($value)
    {
        $this->setupUrl = $value;
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
     * Get the value of enabled
     */
    public function getEnabled()
    {
        return $this->enabled;
    }

    /**
     * Set the value of enabled
     *
     * @param mixed $value
     * @return $this
     */
    public function setEnabled($value)
    {
        $this->enabled = $value;
        return $this;
    }

    /**
     * Get the value of consentAt
     */
    public function getConsentAt()
    {
        return $this->consentAt;
    }

    /**
     * Set the value of consentAt
     *
     * @param mixed $value
     * @return $this
     */
    public function setConsentAt($value)
    {
        $this->consentAt = $value;
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
