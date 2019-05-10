<?php

/**
 * Handles building content permissions for nodes.
 *
 * Any type of node permission may be applied to any node. However, when calculated,
 * only the known permissions for the specified node type are used. For example,
 * gallery permissions could be applied to a forum, causing those permissions to be
 * inherited to any child galleries. However, when compiled, the forum only has
 * forum permissions.
 *
 * @package XenForo_Permissions
 */
class XenForo_ContentPermission_Node implements XenForo_ContentPermission_Interface
{
	/**
	 * Tracks whether we've initialized the node data. Many calls to
	 * {@link rebuildContentPermissions()} may happen on one object.
	 *
	 * @var boolean
	 */
	protected $_nodesInitialized = false;

	/**
	 * Permission model
	 *
	 * @var XenForo_Model_Permission
	 */
	protected $_permissionModel = null;

	/**
	 * Global perms that apply to this call to rebuild the permissions. These permissions
	 * can be manipulated if necessary and the global permissions will actually be modified.
	 *
	 * @var array
	 */
	protected $_globalPerms = array();

	/**
	 * List of node types.
	 *
	 * @var array
	 */
	protected $_nodeTypes = array();

	/**
	 * The node tree hierarchy. This data is traversed to build permissions.
	 *
	 * @var array Format: [parent][node id] => node info
	 */
	protected $_nodeTree = array();

	/**
	 * @var null|array If not null, [node id] => info
	 */
	protected $_nodes = null;

	/**
	 * All node permission entries for the tree, grouped by system, user group, and user.
	 *
	 * @var array
	 */
	protected $_nodePermissionEntries = array();

	/**
	 * Builds the node permissions for a user collection.
	 *
	 * @param XenForo_Model_Permission $permissionModel Permission model that called this.
	 * @param array $userGroupIds List of user groups for the collection
	 * @param integer $userId User ID for the collection, if there are custom permissions
	 * @param array $permissionsGrouped List of all valid permissions, grouped
	 * @param array $globalPerms The global permissions that apply to this combination
	 *
	 * @return array
	 */
	public function rebuildContentPermissions(
		$permissionModel, array $userGroupIds, $userId, array $permissionsGrouped, array &$globalPerms
	)
	{
		$this->_permissionModel = $permissionModel;
		$this->_globalPerms = $globalPerms;

		$this->_nodeSetup();

		$finalPermissions = $this->_buildNodeTreePermissions($userId, $userGroupIds, $globalPerms, $permissionsGrouped);

		$globalPerms = $this->_globalPerms;
		return $finalPermissions;
	}

	/**
	 * Sets up the necessary information about the node tree, existing permission entries,
	 * etc. Only runs if not initialized.
	 */
	protected function _nodeSetup()
	{
		if ($this->_nodesInitialized)
		{
			return;
		}

		$nodeModel = $this->_getNodeModel();

		$this->_nodeTypes = $nodeModel->getAllNodeTypes();
		$this->_nodeTree = $nodeModel->getNodeHierarchy();
		$this->_nodePermissionEntries = $this->_permissionModel->getAllContentPermissionEntriesByTypeGrouped('node');

		$this->_nodesInitialized = true;
	}

	/**
	 * Allows the node data to be injected manually. Generally only needed for testing.
	 *
	 * @param array $nodeTypes
	 * @param array $nodeTree
	 * @param array $nodePermissionEntries
	 */
	public function setNodeDataManually(array $nodeTypes, array $nodeTree, array $nodePermissionEntries)
	{
		$this->_nodeTypes = $nodeTypes;
		$this->_nodeTree = $nodeTree;
		$this->_nodePermissionEntries = $nodePermissionEntries;

		$this->_nodesInitialized = true;
	}

	protected function _getNodesFromTree(array $tree)
	{
		$nodes = array();
		foreach ($tree AS $treeNodes)
		{
			foreach ($treeNodes AS $node)
			{
				$nodes[$node['node_id']] = $node;
			}
		}

		return $nodes;
	}

	public function getContentPermissionDetails(
		$permissionModel, array $userGroupIds, $userId, $contentId, array $permission,
		array $permissionsGrouped, array $globalPerms
	)
	{
		$this->_globalPerms = $globalPerms;
		$this->_permissionModel = $permissionModel;
		$this->_nodeSetup();

		if ($this->_nodes === null)
		{
			$this->_nodes = $this->_getNodesFromTree($this->_nodeTree);
		}

		$path = array();
		$nodeId = $contentId;
		while ($nodeId && isset($this->_nodes[$nodeId]))
		{
			array_unshift($path, $nodeId);
			$nodeId = $this->_nodes[$nodeId]['parent_node_id'];
		}

		$output = array();
		$permissionId = $permission['permission_id'];
		$permissionGroupId = $permission['permission_group_id'];

		$nodeTree = array();
		foreach ($path AS $nodeId)
		{
			$node = $this->_nodes[$nodeId];
			$nodeTree[$node['parent_node_id']][$nodeId] = $node;
		}

		$oldNodeTree = $this->_nodeTree;
		$this->_nodeTree = $nodeTree;

		$finalPermissions = $this->_buildNodeTreePermissions($userId, $userGroupIds, $globalPerms, $permissionsGrouped);
		$this->_nodeTree = $oldNodeTree;

		foreach ($path AS $nodeId)
		{
			$node = $this->_nodes[$nodeId];

			$groupEntries = $this->_getUserGroupNodeEntries($nodeId, $userGroupIds);
			$userEntries = $this->_getUserNodeEntries($nodeId, $userId);
			$nodeWideEntries = $this->_getNodeWideEntries($nodeId);

			$groups = array();
			foreach ($userGroupIds AS $userGroupId)
			{
				$groups[$userGroupId] =
					isset($groupEntries[$userGroupId][$permissionGroupId][$permissionId])
					? $groupEntries[$userGroupId][$permissionGroupId][$permissionId]
					: false;
			}

			$final = null;
			if (isset($this->_nodeTypes[$node['node_type_id']]))
			{
				if ($this->_nodeTypes[$node['node_type_id']]['permission_group_id'] == $permissionGroupId)
				{
					$final = $finalPermissions[$nodeId][$permissionId];
				}
				else if ($permissionGroupId == 'general' && $permissionId == 'viewNode')
				{
					$final = $finalPermissions[$nodeId]['view'];
				}
			}

			$output[$nodeId] = array(
				'title' => $node['title'],
				'user' => isset($userEntries[$permissionGroupId][$permissionId]) ? $userEntries[$permissionGroupId][$permissionId] : false,
				'content' => isset($nodeWideEntries[$permissionGroupId][$permissionId]) ? $nodeWideEntries[$permissionGroupId][$permissionId] : false,
				'groups' => $groups,
				'final' => $final
			);
		}

		return $output;
	}

	/**
	 * Recursively builds node tree permissions for the specified combination.
	 * Note that nodes will have permissions for all node types, but the final
	 * permissions for a node *only* include that node's permissions.
	 *
	 * @param integer $userId
	 * @param array $userGroupIds
	 * @param array $basePermissions Base permissions, coming from global or parent; [group][permission] => allow/unset/etc
	 * @param array $permissionsGrouped List of all valid permissions, grouped
	 * @param integer $parentId ID of the parent node.
	 *
	 * @return array Final permissions (true/false), format: [node id][permission] => value
	 */
	protected function _buildNodeTreePermissions(
		$userId, array $userGroupIds, array $basePermissions, array $permissionsGrouped, $parentId = 0
	)
	{
		if (!isset($this->_nodeTree[$parentId]))
		{
			return array();
		}

		if (!isset($basePermissions['general']['viewNode']))
		{
			if (isset($this->_globalPerms['general']['viewNode']))
			{
				$basePermissions['general']['viewNode'] = $this->_globalPerms['general']['viewNode'];
			}
			else
			{
				$basePermissions['general']['viewNode'] = 'unset';
			}
		}

		$basePermissions = $this->_adjustBasePermissionAllows($basePermissions);

		$finalPermissions = array();

		foreach ($this->_nodeTree[$parentId] AS $node)
		{
			if (!isset($this->_nodeTypes[$node['node_type_id']]))
			{
				continue;
			}

			$nodeType = $this->_nodeTypes[$node['node_type_id']];
			$nodeId = $node['node_id'];

			$groupEntries = $this->_getUserGroupNodeEntries($nodeId, $userGroupIds);
			$userEntries = $this->_getUserNodeEntries($nodeId, $userId);
			$nodeWideEntries = $this->_getNodeWideEntries($nodeId);

			$nodePermissions = $this->_permissionModel->buildPermissionCacheForCombination(
				$permissionsGrouped, $nodeWideEntries, $groupEntries, $userEntries,
				$basePermissions, $passPermissions
			);

			if (!isset($nodePermissions['general']['viewNode']))
			{
				$nodePermissions['general']['viewNode'] = 'unset';
			}

			if ($nodeType['permission_group_id'])
			{
				$nodePermissions[$nodeType['permission_group_id']]['view'] = $nodePermissions['general']['viewNode'];

				$finalNodePermissions = $this->_permissionModel->canonicalizePermissionCache(
					$nodePermissions[$nodeType['permission_group_id']]
				);

				if (isset($finalNodePermissions['view']) && !$finalNodePermissions['view'])
				{
					// forcable deny viewing perms to children if this isn't viewable
					$passPermissions['general']['viewNode'] = 'deny';
				}
			}
			else
			{
				$finalNodePermissions = array();
			}

			$finalPermissions[$nodeId] = $finalNodePermissions;

			$finalPermissions += $this->_buildNodeTreePermissions(
				$userId, $userGroupIds, $passPermissions, $permissionsGrouped, $nodeId
			);
		}

		return $finalPermissions;
	}

	/**
	 * Get all user group entries that apply to this node for the specified user groups.
	 *
	 * @param integer $nodeId
	 * @param array $userGroupIds
	 *
	 * @return array
	 */
	protected function _getUserGroupNodeEntries($nodeId, array $userGroupIds)
	{
		$rawUgEntries = $this->_nodePermissionEntries['userGroups'];
		$groupEntries = array();
		foreach ($userGroupIds AS $userGroupId)
		{
			if (isset($rawUgEntries[$userGroupId], $rawUgEntries[$userGroupId][$nodeId]))
			{
				$groupEntries[$userGroupId] = $rawUgEntries[$userGroupId][$nodeId];
			}
		}

		return $groupEntries;
	}

	/**
	 * Gets all user entries that apply to this node for the specified user ID.
	 *
	 * @param $nodeId
	 * @param $userId
	 *
	 * @return array
	 */
	protected function _getUserNodeEntries($nodeId, $userId)
	{
		$rawUserEntries = $this->_nodePermissionEntries['users'];
		if ($userId && isset($rawUserEntries[$userId], $rawUserEntries[$userId][$nodeId]))
		{
			return $rawUserEntries[$userId][$nodeId];
		}
		else
		{
			return array();
		}
	}

	/**
	 * Get node-wide permissions for this node.
	 *
	 * @param $nodeId
	 *
	 * @return array
	 */
	protected function _getNodeWideEntries($nodeId)
	{
		if (isset($this->_nodePermissionEntries['system'][$nodeId]))
		{
			return $this->_nodePermissionEntries['system'][$nodeId];
		}
		else
		{
			return array();
		}
	}

	/**
	 * Adjusts base (inherited) content_allow values to allow only. This
	 * allows them to be revoked.
	 *
	 * @param array $basePermissions
	 *
	 * @return array Adjusted base perms
	 */
	protected function _adjustBasePermissionAllows(array $basePermissions)
	{
		foreach ($basePermissions AS $group => $p)
		{
			foreach ($p AS $id => $value)
			{
				if ($value === 'content_allow')
				{
					$basePermissions[$group][$id] = 'allow';
				}
			}
		}

		return $basePermissions;
	}

	/**
	 * Force a node-wide reset to override content allow settings from the permissions.
	 * This is used to cause reset to take priority over content allow from a parent, but
	 * not content allow from this node.
	 *
	 * @param array $nodeWideEntries
	 * @param array $nodePermissions
	 *
	 * @return array Updated node permissions
	 */
	protected function _forceNodeWideResetOverride(array $nodeWideEntries, array $nodePermissions)
	{
		foreach ($nodeWideEntries AS $nwGroupId => $nwGroup)
		{
			foreach ($nwGroup AS $nwPermId => $nwValue)
			{
				if ($nwValue === 'reset'
					&& isset($nodePermissions[$nwGroupId][$nwPermId])
					&& $nodePermissions[$nwGroupId][$nwPermId] === 'content_allow'
				)
				{
					$nodePermissions[$nwGroupId][$nwPermId] = 'reset';
				}
			}
		}

		return $nodePermissions;
	}

	/**
	 * Gets the node model object.
	 *
	 * @return XenForo_Model_Node
	 */
	protected function _getNodeModel()
	{
		return XenForo_Model::create('XenForo_Model_Node');
	}
}