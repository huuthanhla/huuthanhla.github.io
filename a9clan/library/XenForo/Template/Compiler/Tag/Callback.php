<?php

/**
* Class to handle compiling template tag calls for "callback".
*
* @package XenForo_Template
*/
class XenForo_Template_Compiler_Tag_Callback implements XenForo_Template_Compiler_Tag_Interface
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
			throw $compiler->getNewCompilerException(new XenForo_Phrase('x_tags_only_used_where_full_statements_allowed', array('tag' => 'callback')));
		}

		if (empty($attributes['class']))
		{
			throw $compiler->getNewCompilerException(new XenForo_Phrase('missing_attribute_x_for_tag_y', array(
				'attribute' => 'class',
				'tag' => 'callback'
			)));
		}

		if (empty($attributes['method']))
		{
			throw $compiler->getNewCompilerException(new XenForo_Phrase('missing_attribute_x_for_tag_y', array(
				'attribute' => 'method',
				'tag' => 'callback'
			)));
		}

		$noEscapeOptions = array_merge($options, array('varEscape' => false));

		$class = $compiler->compileAndCombineSegments($attributes['class'], $noEscapeOptions);
		$method = $compiler->compileAndCombineSegments($attributes['method'], $noEscapeOptions);
		$compiled = $compiler->compileIntoVariable($children, $var, $options);

		if (!empty($attributes['params']))
		{
			$params = $compiler->compileAndCombineSegments($attributes['params'], $noEscapeOptions);
		}
		else
		{
			$params = 'array()';
		}

		$statement = $compiler->getNewRawStatement();
		$statement->addStatement($compiled);
		$statement->addStatement(
			'$' . $compiler->getOutputVar() . ' .= $this->callTemplateCallback(' . $class . ', ' . $method . ', $' . $var . ', ' . $params . ");\n"
			. 'unset($' . $var . ");\n"
		);
		return $statement;
	}
}