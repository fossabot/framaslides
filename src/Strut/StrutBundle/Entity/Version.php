<?php

namespace Strut\StrutBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Strut\StrutBundle\Entity\Presentation;
use JMS\Serializer\Annotation\Exclude;

/**
 * Class Version
 *
 * @ORM\Entity(repositoryClass="Strut\StrutBundle\Repository\VersionRepository")
 * @ORM\Table(name="`version`")
 */

class Version
{
    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     *
     */
    private $id;

    /**
     * @var string
     *
     * @ORM\Column(name="content", type="text", nullable=true)
     *
     */
    private $content;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="updated_at", type="datetime")
     *
     */
    private $updatedAt;

    /**
     * @var Presentation
     * @Exclude
     * @ORM\ManyToOne(targetEntity="Strut\StrutBundle\Entity\Presentation", inversedBy="versions")
     */
    private $presentation;

    /**
     * Version constructor.
     *
     * @param Presentation $presentation
     */
    public function __construct(Presentation $presentation = null)
    {
        $this->presentation = $presentation;
        $this->updatedAt = new \DateTime();
    }

    /**
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @param int $id
     */
    public function setId(int $id)
    {
        $this->id = $id;
    }

    /**
     * @return string
     */
    public function getContent(): string
    {
        return $this->content;
    }

    /**
     * @param string $content
     */
    public function setContent(string $content)
    {
        $this->content = $content;
    }

    /**
     * @return \DateTime
     */
    public function getUpdatedAt(): \DateTime
    {
        return $this->updatedAt;
    }

    /**
     * @param \DateTime $updatedAt
     */
    public function setUpdatedAt(\DateTime $updatedAt)
    {
        $this->updatedAt = $updatedAt;
    }

    /**
     * Set presentation
     *
     * @param Presentation $presentation
     *
     * @return Version
     */
    public function setPresentation(Presentation $presentation = null)
    {
        $this->presentation = $presentation;

        return $this;
    }

    /**
     * Get presentation
     *
     * @return Presentation
     */
    public function getPresentation(): Presentation
    {
        return $this->presentation;
    }
}
