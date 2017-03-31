<?php

namespace Repositories;

use D2EM;
use Doctrine\ORM\EntityRepository;
use Entities\PatchPanelPort as PatchPanelPortEntity;
use Entities\PatchPanelPortHistory as PatchPanelPortHistoryEntity;
use Entities\PatchPanelPortHistoryFile as PatchPanelPortHistoryFileEntity;

/**
 * Cabinet
 *
 * This class was generated by the Doctrine ORM. Add your own custom
 * repository methods below.
 */
class PatchPanelPort extends EntityRepository
{
    public function getAllPatchPanelPort(int $patchPanelId = null):array {
        $dql = "SELECT ppp
                    FROM Entities\\PatchPanelPort ppp
                    JOIN ppp.patchPanel pp
                    WHERE ppp.duplexMasterPort IS NULL";

        if($patchPanelId != null){
            $dql .= " AND pp.id = $patchPanelId";
        }

        $listPatchPanelPort = $this->getEntityManager()->createQuery( $dql )->getResult();

        return $listPatchPanelPort;
    }

    public function getPatchPanelPortAvailableForDuplex(int $patchPanelId, int $portId):array {
        $dql = "SELECT ppp
                    FROM Entities\\PatchPanelPort ppp
                    JOIN ppp.patchPanel pp
                    WHERE
                        pp.id = $patchPanelId
                        AND ppp.id != $portId
                    AND ppp.duplexMasterPort IS NULL
                    
                   AND ppp.state = ".\Entities\PatchPanelPort::STATE_AVAILABLE;

        $availablePorts = $this->getEntityManager()->createQuery( $dql )->getResult();

        $listAvailablePort = array();

        foreach($availablePorts as $port){
            $listAvailablePort[$port->getId()] = $port->getPatchPanel()->getPortPrefix().$port->getNumber();
        }
        return $listAvailablePort;
    }

    public function isSwitchPortAvailable($switchPortId, $patchPanelPortId = null){
        $dql = "SELECT count(ppp.id)
                    FROM Entities\\PatchPanelPort ppp
                    WHERE
                        ppp.switchPort = $switchPortId";

        $query = $this->getEntityManager()->createQuery( $dql );
        $nb = $query->getSingleScalarResult();
        return ($nb > 0) ? false : true;
    }


    /**
     * Return the mailable class name for a given email type
     * @param int $type Email type
     * @return string Class name (or null)
     */
    public function resolveEmailClass( int $type ) {
        return isset( PatchPanelPortEntity::$EMAIL_CLASSES[ $type ] ) ? PatchPanelPortEntity::$EMAIL_CLASSES[ $type ] : null;
    }


    /**
     * Archive a patch panel port (and its slave ports)
     *
     * NB: does not reset the original port. This this, use:
     *
     *     $ppp->resetPatchPanelPort()
     *
     * @param PatchPanelPortEntity $ppp
     * @return PatchPanelPortHistoryEntity
     */
    public function archive( PatchPanelPortEntity $ppp ): PatchPanelPortHistoryEntity {

        $ppph = new PatchPanelPortHistoryEntity();
        $ppph->setFromPatchPanelPort($ppp);
        $ppp->addPatchPanelPortHistory( $ppph );

        D2EM::persist($ppph);

        if( $ppp->hasSlavePort() ) {
            foreach( $ppp->getDuplexSlavePorts() as $pppsp ) {
                $sph = clone $ppph;
                $sph->setNumber( $pppsp->getNumber() );
                $sph->setDuplexMasterPort( $ppph );
                $sph->setPatchPanelPort( $pppsp );
                D2EM::persist( $sph );
            }
        }

        foreach( $ppp->getPatchPanelPortPublicFiles() as $pppf ) {
            $ppphf = new PatchPanelPortHistoryFileEntity;
            $ppphf->setFromPatchPanelPortFile( $pppf );
            $ppph->addPatchPanelPortHistoryFile( $ppphf );
        }

        return $ppph;
    }


    /**
     * Load patch panel port objects allow them to be filtered by location, cabinet and/or cable type.
     *
     * @param int $location   Location ID (or zero for all)
     * @param int $cabinet    Cabinet ID
     * @param int $cabletype  Cable type (@see \Entities\PatchPanel::$CABLE_TYPES)
     * @return array
     */
    public function advancedSearch( int $location, int $cabinet, int $cabletype ) {
        $dql = "SELECT ppp
                  FROM Entities\PatchPanelPort ppp
                      LEFT JOIN ppp.patchPanel pp
                      LEFT JOIN pp.cabinet cab
                      LEFT JOIN cab.Location l ";

        $wheres = [];

        if( $location ) {
            $wheres[] = "l.id = " . $location;
        }

        if( $cabinet ) {
            $wheres[] = "cab.id = " . $cabinet;
        }

        if( $cabletype ) {
            $wheres[] = "pp.cable_type = " . $cabletype;
        }

        if( count( $wheres ) ) {
            $dql .= 'WHERE ' . implode(' AND ', $wheres);
        }

        $dql .= " ORDER BY pp.id ASC, ppp.number ASC";

        return $this->getEntityManager()->createQuery( $dql )->getResult();
    }

}
