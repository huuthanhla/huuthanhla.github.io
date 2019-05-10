<?php

/**
* Class to handle compiling template tag calls for "container".
*
* @package XenForo_Template
*/
class XenForo_Template_Compiler_Tag_Container implements XenForo_Template_Compiler_Tag_Interface
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
			throw $compiler->getNewCompilerException(new XenForo_Phrase('x_tags_only_used_where_full_statements_allowed', array('tag' => 'container')));
		}

		if (empty($attributes['var']))
		{
			throw $compiler->getNewCompilerException(new XenForo_Phrase('missing_container_tag_var_attribute'));
		}

		$var = $compiler->compileVarRef($attributes['var'], array_merge($options, array('disableVarMap' => true)));
		$var = preg_replace('/^\$([^[\']*)/', '__extraData[\'$1\']', $var);

		$childOutput = $compiler->compileIntoVariable($children, $var, $options, false);

		$statement = $compiler->getNewRawStatement();
		$statement->addStatement($childOutput);

		return $statement;
	}
}