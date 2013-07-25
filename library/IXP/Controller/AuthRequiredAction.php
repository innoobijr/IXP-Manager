<?php

/*
 * Copyright (C) 2009-2012 Internet Neutral Exchange Association Limited.
 * All Rights Reserved.
 *
 * This file is part of IXP Manager.
 *
 * IXP Manager is free software: you can redistribute it and/or modify it
 * under the terms of the GNU General Public License as published by the Free
 * Software Foundation, version v2.0 of the License.
 *
 * IXP Manager is distributed in the hope that it will be useful, but WITHOUT
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or
 * FITNESS FOR A PARTICULAR PURPOSE.  See the GNU General Public License for
 * more details.
 *
 * You should have received a copy of the GNU General Public License v2.0
 * along with IXP Manager.  If not, see:
 *
 * http://www.gnu.org/licenses/gpl-2.0.html
 */


/**
 * INEX's version of Zend's Zend_Controller_Action implemented custom
 * functionality.
 *
 * All application controlers subclass this rather than Zend's version directly.
 *
 * @package IXP_Controller
 *
 */
class IXP_Controller_AuthRequiredAction extends IXP_Controller_Action
{
    use OSS_Controller_Action_Trait_AuthRequired;
    
    
    /**
     * Load a customer from the database by shortname but redirect to `error/error` if no such customer.
     *
     * Will use 'shortname' parameter is no shortname provided
     *
     * @param string|bool $shortname The customer shortname to load (or, if false, look for `shortname` parameter)
     * @param string $redirect Alternative location to redirect to
     * @return \Entities\Customer The customer object
     */
    protected function loadCustomerByShortname( $shortname = false, $redirect = null )
    {
        if( $shortname === false )
            $shortname = $this->getParam( 'shortname', false );
    
        if( $shortname )
            $c = $this->getD2EM()->getRepository( '\\Entities\\Customer' )->findOneBy( [ 'shortname' => $shortname ] );
    
        if( !$shortname || !$c )
        {
            $this->addMessage( 'Invalid customer', OSS_Message::ERROR );
            $this->redirect( $redirect === null ? 'error/error' : $redirect );
        }
    
        return $c;
    }
    
    /**
     * Load a customer from the database by ID but redirect to `error/error` if no such customer.
     *
     * @param int $id The customer ID to load
     * @param string $redirect Alternative location to redirect to
     * @return \Entities\Customer The customer object
     */
    protected function loadCustomerById( $id, $redirect = null )
    {
        if( $id )
            $c = $this->getD2R( '\\Entities\\Customer' )->find( $id );
    
        if( !$id || !$c )
        {
            $this->addMessage( "Could not load the requested customer object", OSS_Message::ERROR );
            $this->redirect( $redirect === null ? 'error/error' : $redirect );
        }
    
        return $c;
    }
    
    
    /**
     * Utility function to load a customer's notes and calculate the amount of unread / updated notes
     * for the logged in user and the given customer
     *
     * Used by:
     * @see CustomerController
     * @see DashboardController
     *
     * @param \Entities\Customer $cust
     */
    protected function _fetchCustomerNotes( $custid, $publicOnly = false )
    {
        $this->view->custNotes = $custNotes = $this->getD2EM()->getRepository( '\\Entities\\CustomerNote' )->ordered( $custid, $publicOnly );
        $unreadNotes = 0;
         
        $rut = $this->getUser()->getPreference( "customer-notes.read_upto" );
        $lr  = $this->getUser()->getPreference( "customer-notes.{$custid}.last_read" );
        
        if( $lr || $rut )
        {
            foreach( $custNotes as $cn )
            {
                $time = $cn->getUpdated()->format( "U" );
                if( ( !$rut || $rut < $time ) && ( !$lr || $lr < $time ) )
                    $unreadNotes++;
            }
        }
        else
            $unreadNotes = count( $custNotes );
    
        $this->view->notesReadUpto = $rut;
        $this->view->notesLastRead = $lr;
        $this->view->unreadNotes   = $unreadNotes;
    }
    
    
    
}

