<?php
namespace PHPHtmlParser\Dom;

use PHPHtmlParser\Selector;

/**
 * Dom node object.
 */
class Node {

	/**
	 * Contains the tag name/type
	 *
	 * @var string
	 */
	protected $tag;

	/**
	 * Contains a list of attributes on this tag.
	 *
	 * @var array
	 */
	protected $attr = [];

	/**
	 * An array of all the children.
	 *
	 * @var array
	 */
    protected $children = [];

    /**
     * Contains the parent Node.
     *
     * @var Node
     */
    protected $parent = null;

	/**
	 * The unique id of the class. Given by PHP.
	 *
	 * @string
	 */
   	protected $id;

	public function __construct()
	{
		$this->id = spl_object_hash($this);
	}

	/**
	 * Magic get method for attributes and certain methods.
	 *
	 * @param string $key
	 * @return mixed
	 */
    public function __get($key)
    {
    	// check attribute first
        if ( ! is_null($this->tag->getAttribute($key)))
        {
            return $this->tag->getAttribute($key);
        }
        switch (strtolower($key))
        {
            case 'outerhtml': 
            	return $this->outerHtml();
            case 'innerhtml': 
            	return $this->innerHtml();
            case 'text': 
            	return $this->text();
        }
		
        return null;
    }

	/**
	 * @todo Remove the need for this to be called.
	 */
    public function __destruct()
    {
        $this->clear();
    }

	/**
	 * Simply calls the outer text method.
     *
     * @return string
	 */
    public function __toString()
    {
        return $this->outerHtml();
    }

	/**
	 * Returns the id of this object.
	 */
    public function id()
    {
    	return $this->id;
    }

	/**
     * Returns the parent of node.
     *
     * @return Node
     */
    public function getParent()
    {
    	return $this->parent;
   	}

	/**
	 * Sets the parent node.
	 *
	 * @param Node $parent
	 * @chainable
	 */
   	public function setParent(Node $parent)
   	{
   		// remove from old parent
   		if ( ! is_null($this->parent))
   		{
   			if ($this->parent->id() == $parent->id())
   			{
   				// already the parent
   				return $this;
   			}

   			$this->parent->removeChild($this->id);
   		}

        $this->parent = $parent;

        // assign child to parent
        $this->parent->addChild($this);

        return $this;
    }

	/**
	 * Checks if this node has children.
	 *
	 * @return bool
	 */
    public function hasChildren()
    {
        return ! empty($this->children);
    }

    /**
     * Returns the child by id.
     *
     * @param int $id
     * @return Node
     * @throw Exception
     */
    public function getChild($id)
    {
    	if ( ! isset($this->children[$id]))
    	{
    		throw new Exception('Child "'.$id.'" not found in this node.');
    	}

        return $this->children[$id]['node'];
    }

	/**
	 * Adds a child node to this node and returns the id of the child for this
	 * parent.
	 * 
	 * @param Node $child
	 * @return bool
	 */
    public function addChild(Node $child)
    {
    	$key     = null;
    	$newKey  = 0;

    	// check if child is itself
    	if ($child->id() == $this->id)
    	{
    		throw new Exception('Can not set itself as a child.');
    	}

    	if ($this->hasChildren())
    	{
    		if (isset($this->children[$child->id()]))
    		{
    			// we already have this child
    			return false;
    		}
    		$sibling = $this->lastChild();
    		$key     = $sibling->id();
    		$this->children[$key]['next'] = $child->id();
    	}

		// add the child
    	$this->children[$child->id()] = [
    		'node' => $child,
    		'next' => null,
    		'prev' => $key,
    	];

    	// tell child I am the new parent
    	$child->setParent($this);

    	return true;
    }

	/**
	 * Removes the child by id.
	 *
	 * @param int $id
	 * @chainable
	 */
	public function removeChild($id)
	{
		if ( ! isset($this->children[$id]))
			return $this;

		// handle moving next and previous assignments.
		$next = $this->children[$id]['next'];
		$prev = $this->children[$id]['prev'];
		if ( ! is_null($next))
		{
			$this->children[$next]['prev'] = $prev;
		}
		if ( ! is_null($prev))
		{
			$this->children[$prev]['next'] = $next;
		}
		
		// remove the child
		unset($this->children[$id]);

		return $this;
	}

	/**
	 * Attempts to get the next child.
	 *
	 * @param int $id
	 * @return Node
	 * @uses $this->getChild()
 	 */
	public function nextChild($id)
	{
		$child = $this->getChild($id);
		$next  = $this->children[$child->id()]['next'];
		return $this->getChild($next);
	}

	/**
	 * Attempts to get the previous child.
	 *
	 * @param int $id
	 * @return Node
	 * @uses $this->getChild()
	 */
	public function previousChild($id)
	{
		$child = $this->getchild($id);
		$next  = $this->children[$child->id()]['prev'];
		return $this->getChild($next);
	}

	/**
	 * Checks if the give node id is a decendant of the 
	 * current node.
	 *
	 * @param int $id
	 * @return bool
	 */
	public function isDescendant($id)
	{
		foreach ($this->children as $childId => $child)
		{
			if ($id == $childId)
			{
				return true;
			}
			elseif ($child->hasChildren())
			{
				if ($child->isDescendant($id))
				{
					return true;
				}
			}
		}

		return false;
	}

    /**
     * Shortcut to return the first child.
     *
     * @return Node
     * @uses $this->getChild()
     */
    public function firstChild()
    {
    	reset($this->children);
    	$key = key($this->children);
    	return $this->getChild($key);
    }

    /**
     * Attempts to get the last child.
     *
     * @return Node
     */
    public function lastChild()
    {
    	end($this->children);
    	$key = key($this->children);
    	return $this->getChild($key);
    }

    /**
     * Attempts to get the next sibling.
     *
     * @return Node
     * @throws Exception
     */
    public function nextSibling()
    {
    	if (is_null($this->parent))
    	{
    		throw new Exception('Parent is not set for this node.');
    	}

    	return $this->parent->nextChild($this->id);
    }

	/**
	 * Attempts to get the previous sibling
	 *
	 * @return Node
	 * @throw Exception
	 */
    public function previousSibling()
    {
    	if (is_null($this->parent))
    	{
    		throw new Exception('Parent is not set for this node.');
    	}

    	return $this->parent->previousChild($this->id);
    }

	/**
	 * Gets the tag object of this node.
	 *
	 * @return Tag
	 */
    public function getTag()
    {
    	return $this->tag;
    }

	/**
	 * A wrapper method that simply calls the getAttribute method
	 * on the tag of this node.
	 *
	 * @return array
	 */
    public function getAttributes()
    {
    	return $this->tag->getAttributes();
    }

	/**
	 * A wrapper method that simply calls the getAttributes method
	 * on the tag of this node.
	 *
	 * @param string $key
	 * @return mixed
	 */
    public function getAttribute($key)
    {
    	return $this->tag->getAttribute($key);
    }

	/**
     * Function to locate a specific ancestor tag in the path to the root.
     *
     * @param  string $tag
     * @return Node
     * @throws Exception
     */
    public function ancestorByTag($tag)
    {
        // Start by including ourselves in the comparison.
        $node = $this;

        while ( ! is_null($node))
        {
            if ($node->tag->name() == $tag)
            {
            	return $node;
            }

            $node = $node->getParent();
        }

    	throw new Exception('Could not find an ancestor with "'.$tag.'" tag');
    }

    /**
     * Find elements by css selector
     *
     * @param string $selector
     * @param int    $nth
     * @return array
     */
    public function find($selector, $nth = null)
    {
    	$selector = new Selector($selector);
    	$nodes    = $selector->find($this);

        if ( ! is_null($nth))
        {
        	// return nth-element or array
        	if (isset($nodes[$nth]))
        		return $nodes[$nth];
        	
        	return null;
        }

        return $nodes;
    }

    /**
     * Function to try a few tricks to determine the displayed size of an img on the page.
     * NOTE: This will ONLY work on an IMG tag. Returns FALSE on all other tag types.
     *
     * Future enhancement:
     * Look in the tag to see if there is a class or id specified that has a height or width attribute to it.
     *
     * Far future enhancement
     * Look at all the parent tags of this image to see if they specify a class or id that has an img selector that specifies a height or width
     * Note that in this case, the class or id will have the img subselector for it to apply to the image.
     *
     * ridiculously far future development
     * If the class or id is specified in a SEPARATE css file thats not on the page, go get it and do what we were just doing for the ones on the page.
     *
     * @author John Schlick
     * @return array an array containing the 'height' and 'width' of the image on the page or -1 if we can't figure it out.
     */
    public function get_display_size()
    {
        $width = -1;
        $height = -1;

        if ($this->tag->name() != 'img')
        {
            return false;
        }

        // See if there is a height or width attribute in the tag itself.
        if ( ! is_null($this->tag->getAttribute('width')))
        {
            $width = $this->tag->getAttribute('width');
        }

        if ( ! is_null($this->tag->getAttribute('height')))
        {
            $height = $this->tag->getAttribute('height');
        }

        // Now look for an inline style.
        if ( ! is_null($this->tag->getAttribute('style')))
        {
            // Thanks to user gnarf from stackoverflow for this regular expression.
            $attributes = [];
            preg_match_all("/([\w-]+)\s*:\s*([^;]+)\s*;?/", $this->tag->getAttribute['style'], $matches, PREG_SET_ORDER);
            foreach ($matches as $match) 
            {
            	$attributes[$match[1]] = $match[2];
            }

            // If there is a width in the style attributes:
            if (isset($attributes['width']) and $width == -1)
            {
                // check that the last two characters are px (pixels)
                if (strtolower(substr($attributes['width'], -2)) == 'px')
                {
                    $proposed_width = substr($attributes['width'], 0, -2);
                    // Now make sure that it's an integer and not something stupid.
                    if (filter_var($proposed_width, FILTER_VALIDATE_INT))
                    {
                        $width = $proposed_width;
                    }
                }
            }

            // If there is a width in the style attributes:
            if (isset($attributes['height']) and $height == -1)
            {
                // check that the last two characters are px (pixels)
                if (strtolower(substr($attributes['height'], -2)) == 'px')
                {
                    $proposed_height = substr($attributes['height'], 0, -2);
                    // Now make sure that it's an integer and not something stupid.
                    if (filter_var($proposed_height, FILTER_VALIDATE_INT))
                    {
                        $height = $proposed_height;
                    }
                }
            }

        }

        $result = [
        	'height' => $height,
            'width'  => $width
      	];
        return $result;
    }

    /**
     * clean up memory due to php5 circular references memory leak...
     *
     * @todo Remove the need for this. (Remove circular references)
     */
    protected function clear()
    {
        $this->nodes    = null;
        $this->parent   = null;
        $this->children = null;
    }

}