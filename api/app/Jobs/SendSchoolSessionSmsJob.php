<?php

namespace App\Jobs;

/**
 * @deprecated Renomme en SendSmsJob : ce job sert aussi aux liens de galerie
 *             (GalleryController::sendAccessSms). Conserve une version pour que les jobs
 *             deja en file au moment du deploiement puissent encore etre deserialises.
 */
class SendSchoolSessionSmsJob extends SendSmsJob {}
