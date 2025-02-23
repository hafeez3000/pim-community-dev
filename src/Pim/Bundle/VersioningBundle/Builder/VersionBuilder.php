<?php

namespace Pim\Bundle\VersioningBundle\Builder;

use Doctrine\Common\Util\ClassUtils;
use Pim\Bundle\VersioningBundle\Model\Version;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Version builder
 *
 * @author    Nicolas Dupont <nicolas@akeneo.com>
 * @copyright 2013 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class VersionBuilder
{
    /**
     * @var NormalizerInterface
     */
    protected $normalizer;

    /**
     * @param NormalizerInterface $normalizer
     */
    public function __construct(NormalizerInterface $normalizer)
    {
        $this->normalizer = $normalizer;
    }

    /**
     * Build a version for a versionable entity
     *
     * @param object       $versionable
     * @param string       $author
     * @param Version|null $previousVersion
     * @param string|null  $context
     *
     * @return Version
     */
    public function buildVersion($versionable, $author, Version $previousVersion = null, $context = null)
    {
        $resourceName = ClassUtils::getClass($versionable);
        $resourceId   = $versionable->getId();

        $versionNumber = $previousVersion ? $previousVersion->getVersion() + 1 : 1;
        $oldSnapshot   = $previousVersion ? $previousVersion->getSnapshot() : [];

        // TODO: we don't use direct json serialize due to convert to audit data based on array_diff
        $snapshot = $this->normalizer->normalize($versionable, 'csv', array('versioning' => true));

        $changeset = $this->buildChangeset($oldSnapshot, $snapshot);

        $version = new Version($resourceName, $resourceId, $author, $context);
        $version->setVersion($versionNumber)
            ->setSnapshot($snapshot)
            ->setChangeset($changeset);

        return $version;
    }

    /**
     * Create a pending version for a versionable entity
     *
     * @param object      $versionable
     * @param string      $author
     * @param array       $changeset
     * @param string|null $context
     *
     * @return Version
     */
    public function createPendingVersion($versionable, $author, array $changeset, $context = null)
    {
        $resourceName = ClassUtils::getClass($versionable);

        $version = new Version($resourceName, $versionable->getId(), $author, $context);
        $version->setChangeset($changeset);

        return $version;
    }

    /**
     * Build a pending version
     *
     * @param Version      $pending
     * @param Version|null $previousVersion
     *
     * @return Version
     */
    public function buildPendingVersion(Version $pending, Version $previousVersion = null)
    {
        $versionNumber = $previousVersion ? $previousVersion->getVersion() + 1 : 1;
        $oldSnapshot   = $previousVersion ? $previousVersion->getSnapshot() : [];

        $modification = $pending->getChangeset();
        $snapshot     = $modification + $oldSnapshot;
        $changeset    = $this->buildChangeset($oldSnapshot, $snapshot);

        $pending->setVersion($versionNumber)
            ->setSnapshot($snapshot)
            ->setChangeset($changeset);

        return $pending;
    }

    /**
     * Build the changeset
     *
     * @param array $oldSnapshot
     * @param array $newSnapshot
     *
     * @return array
     */
    protected function buildChangeset(array $oldSnapshot, array $newSnapshot)
    {
        return $this->filterChangeset($this->mergeSnapshots($oldSnapshot, $newSnapshot));
    }

    /**
     * Merge the old and new snapshots
     *
     * @param array $oldSnapshot
     * @param array $newSnapshot
     *
     * @return array
     */
    protected function mergeSnapshots(array $oldSnapshot, array $newSnapshot)
    {
        $newSnapshot = array_map(
            function ($newItem) {
                return ['new' => $newItem];
            },
            $newSnapshot
        );

        $oldSnapshot = array_map(
            function ($oldItem) {
                return ['old' => $oldItem];
            },
            $oldSnapshot
        );

        $mergedSnapshot = array_merge_recursive($newSnapshot, $oldSnapshot);

        return array_map(
            function ($mergedItem) {
                return [
                    'old' => array_key_exists('old', $mergedItem) ? $mergedItem['old'] : '',
                    'new' => array_key_exists('new', $mergedItem) ? $mergedItem['new'] : ''
                ];
            },
            $mergedSnapshot
        );
    }

    /**
     * Filter changeset to remove values that are the same
     *
     * @param array $changeset
     *
     * @return array
     */
    protected function filterChangeset(array $changeset)
    {
        return array_filter(
            $changeset,
            function ($item) {
                return $item['old'] != $item['new'];
            }
        );
    }
}
