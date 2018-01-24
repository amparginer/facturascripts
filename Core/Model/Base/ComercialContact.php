<?php
/**
 * This file is part of FacturaScripts
 * Copyright (C) 2013-2018    Carlos Garcia Gomez  <carlos@facturascripts.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */
namespace FacturaScripts\Core\Model\Base;

use FacturaScripts\Core\App\AppSettings;
use FacturaScripts\Core\Base\Utils;
use FacturaScripts\Core\Lib\IDFiscal;
use FacturaScripts\Core\Lib\RegimenIVA;

/**
 * Description of ComercialContact
 *
 * @author Carlos García Gómez <carlos@facturascripts.com>
 */
abstract class ComercialContact extends Contact
{

    /**
     * Identifier code of the customer.
     *
     * @var string
     */
    public $codcliente;

    /**
     * Default payment method for this customer.
     *
     * @var string
     */
    public $codpago;

    /**
     * Identifier code of the supplier.
     *
     * @var string
     */
    public $codproveedor;

    /**
     * Default series for this customer.
     *
     * @var string
     */
    public $codserie;

    /**
     * Accounting code.
     *
     * @var string
     */
    public $codsubcuenta;

    /**
     * True -> the customer no longer buys us or we do not want anything with him.
     *
     * @var boolean
     */
    public $debaja;

    /**
     * Date on which the customer was discharged.
     *
     * @var string
     */
    public $fechabaja;

    /**
     * Type of fiscal identifier.
     *
     * @var IDFiscal
     */
    private static $idFiscal;

    /**
     * True -> the customer is a natural person.
     * False -> the client is a legal person (company).
     *
     * @var boolean
     */
    public $personafisica;

    /**
     * Social reason of the client, that is, the official name. The one that appears on the invoices.
     *
     * @var string
     */
    public $razonsocial;

    /**
     * Taxation regime of the provider. For now they are only implemented general and exempt.
     *
     * @var string
     */
    public $regimeniva;

    /**
     * Type of VAT regime
     *
     * @var RegimenIVA
     */
    private static $regimenIVA;

    /**
     * Type of tax identification of the client.
     * Examples: CIF, NIF, CUIT ...
     *
     * @var string
     */
    public $tipoidfiscal;

    /**
     * Website of the person.
     *
     * @var string
     */
    public $web;

    abstract public function getDirecciones();

    public function __construct($data = [])
    {
        if (self::$idFiscal === null) {
            self::$idFiscal = new IDFiscal();
            self::$regimenIVA = new RegimenIVA();
        }

        parent::__construct($data);
    }

    public function clear()
    {
        parent::clear();
        $this->cifnif = '';
        $this->codpago = AppSettings::get('default', 'codpago');
        $this->debaja = false;
        $this->personafisica = true;
        $this->regimeniva = self::$regimenIVA->defaultValue();
        $this->tipoidfiscal = self::$idFiscal->defaultValue();
    }

    public function test()
    {
        parent::test();
        $this->razonsocial = Utils::noHtml($this->razonsocial);
        $this->web = Utils::noHtml($this->web);

        if (empty($this->razonsocial)) {
            $this->razonsocial = $this->nombre;
        }

        if (!$this->debaja) {
            $this->fechabaja = null;
        } else if (empty($this->fechabaja)) {
            $this->fechabaja = date('d-m-Y');
        }
    }
}