<?php

namespace App\Modules\Import\Services\WooCommerce;

/**
 * Corespondența URL-urilor siteului vechi cu cele noi (docs/STRUCTURA-SITE-SI-FLUXURI.md §2). Fiecare URL are un
 * echivalent propriu; nimic nu este trimis grămadă spre prima pagină. Ținta goală + 410 = conținut demonstrativ
 * al temei, eliminat definitiv.
 */
final class LegacyUrlMap
{
    /** @return array<string, array{0:string, 1:int}> */
    public static function rules(): array
    {
        return [
            '/shop/' => ['/rame/', 301],
            '/categorie-produs/rame/' => ['/rame/', 301],
            '/categorie-produs/rame/femei/' => ['/rame/femei/', 301],
            '/categorie-produs/rame/barbati/' => ['/rame/barbati/', 301],
            '/categorie-produs/rame/copii/' => ['/rame/copii/', 301],
            '/categorie-produs/rame/unisex-rame/' => ['/rame/unisex/', 301],
            '/categorie-produs/rame/metal/' => ['/rame/metal/', 301],
            '/categorie-produs/rame/acetat/' => ['/rame/acetat/', 301],
            '/categorie-produs/rame/plastic/' => ['/rame/plastic/', 301],
            '/categorie-produs/rame/titan/' => ['/rame/titan/', 301],
            '/categorie-produs/clip-on-acetate/' => ['/rame/clip-on/', 301],
            '/categorie-produs/clip-on-metal/' => ['/rame/clip-on/', 301],
            '/categorie-produs/clip-on-plastic/' => ['/rame/clip-on/', 301],
            '/fabrica-de-lentile/' => ['/productia-noastra/', 301],
            '/about-us-3/' => ['/despre-noi/', 301],
            '/contact-us/' => ['/contact/', 301],
            '/contul-meu/' => ['/cont/', 301],
            '/finalizare/' => ['/finalizare-comanda/', 301],
            '/blog/' => ['/ghiduri/', 301],
            '/2025/05/26/cum-sa-intretii-corect-ochelarii-de-vedere-ca-sa-dureze-mai-mult/' => ['/ghiduri/cum-sa-intretii-corect-ochelarii-de-vedere/', 301],
            '/2025/05/26/cum-sa-alegi-ochelarii-de-vedere-potriviti-pentru-forma-fetei-tale/' => ['/ghiduri/cum-alegi-rama-dupa-forma-fetei/', 301],
            '/politica-de-cookie/' => ['/politica-de-cookie-uri/', 301],
            '/ochelari-de-vedere-romana/' => ['/rame/', 301],
            '/ochelari-de-vedere-magyar/' => ['/rame/', 301],
            '/category/optoplus/' => ['/ghiduri/', 301],
            '/category/fara-categorie/' => ['/ghiduri/', 301],
            '/sample-page/' => ['', 410],
            '/wishlist/' => ['', 410],
            '/compare/' => ['', 410],
            '/portfolio/' => ['', 410],
            '/portfolio/potenti-parturient-parturie/' => ['', 410],
            '/portfolio/rhoncus-quisque-sollicitudin/' => ['', 410],
            '/portfolio/a-lacus-bibendum-pulvinar/' => ['', 410],
            '/portfolio/leo-uteu-ullamcorper/' => ['', 410],
            '/portfolio/venenatis-nam-phasellus/' => ['', 410],
            '/portfolio/imperdiet-mauris-a-nontin/' => ['', 410],
            '/portfolio/et-vestibulum-quis-a-suspendisse/' => ['', 410],
            '/portfolio/netus-eu-mollis-hac-dignis/' => ['', 410],
            '/portfolio/suspendisse-quam-at-vestibulum/' => ['', 410],
            '/author/csergozsofi98/' => ['', 410],
            '/author/jeremias-laszlo/' => ['', 410],
            '/author/vitim/' => ['', 410],
        ];
    }

    /**
     * Etichetele de produs (/eticheta-produs/<x>/) dublează categoriile și mărcile: redirect spre echivalent.
     *
     * @return array<string, string>
     */
    public static function tagTargets(): array
    {
        return [
            'femei' => '/rame/femei/', 'barbati' => '/rame/barbati/', 'copii' => '/rame/copii/', 'unisex' => '/rame/unisex/',
            'metal' => '/rame/metal/', 'acetat' => '/rame/acetat/', 'plastic' => '/rame/plastic/', 'titan' => '/rame/titan/',
            'clip-on-acetate' => '/rame/clip-on/', 'clip-on-acetat' => '/rame/clip-on/', 'clip-on-metal' => '/rame/clip-on/', 'clip-on-plastic' => '/rame/clip-on/',
        ];
    }
}
