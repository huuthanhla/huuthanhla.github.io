<?php

/**
* Class to handle compiling template tag calls for "set".
*
* @package XenForo_Template
*/
class XenForo_Template_Compiler_Tag_Set implements XenForo_Template_Compiler_Tag_Interface
{
	/**
	* Compile the specified tag and return PHP code to handle it.
	*
	* @param XenForo_Template_Compiler The invoking compiler
	* @param string                 Name of the tag called
	* @param array                  Attributes for the tag (may be empty)
	* @param array                  Nodes (tags/curlies/text) within this tag (may be empty)
	* @param array                  Compilation options
	*
	* @return string
	*/
	public function compile(XenForo_Template_Compiler $compiler, $tag, array $attributes, array $children, array $options)
	{
		if (empty($options['allowRawStatements']))
		{
			throw $compiler->getNewCompilerException(new XenForo_Phrase('x_tags_only_used_where_full_statements_allowed', array('tag' => 'set')));
		}

		if (empty($attributes['var']))
		{
			throw $compiler->getNewCompilerException(new XenForo_Phrase('missing_attribute_x_for_tag_y', array(
				'attribute' => 'var',
				'tag' => 'set'
			)));
		}

		$var = $compiler->compileVarRef($attributes['var'], array_merge($options, array('disableVarMap' => true)));
		$var = substr($var, 1); // need to take off leading $

		if (empty($children))
		{
			$children = null;
		}

		if (!empty($attributes['value']))
		{
			if ($children)
			{
				throw $compiler->getNewCompilerException(new XenForo_Phrase('tag_contained_children_and_value_attribute'));
			}

			$value = $compiler->compileAndCombineSegments($attributes['value'], array_merge($options, array('varEscape' => false)));
			$childOutput = '$' . $var . ' = ' . $value . ";\n";
		}
		else
		{
			$childOutput = $compiler->compileIntoVariable($children, $var, $options, false);
		}

		$statement = $compiler->getNewRawStatement();
		$statement->addStatement($childOutput);

		return $statement;
	}
}