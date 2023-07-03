.. include:: /Includes.rst.txt

.. _configuration-tsconfig:

==================
TsConfig
==================

This site covers options to configure the plugin.
All written options can be overwritten with constants.

.. _configuration-custom-template:

Custom view templates
====================

..  attention::
    Please note: DocCheck only supports the usage of their login forms which are provided via their domain.

.. confval:: templateLayouts

    :type: array

    You might want to change the HTML code that renders the iFrame.

    To do so, copy the :file:`Resources/Private/Templates/DocCheckAuthentication/` and :file:`Resources/Private/Partials/DocCheckAuthentication/` folder to, for example, your :file:`<yourPathOfChoice>/Templates/DocCheckAuthentication` and set the following constants in your TYPO3 setup:

    Example::

      plugin.tx_typo3docchecklogin.view.templateRootPath = <yourPathOfChoice>/Templates/DocCheckAuthentication
      plugin.tx_typo3docchecklogin.view.partialRootPaths = <yourPathOfChoice>/Partials/DocCheckAuthentication


.. _configuration-overwrite-loginid:

Overwrite Login ID
====================

.. confval:: loginOverrideId

    :type: string
    :Default: null

    This numeric parameter overrides the used DocCheck Login ID. Especially useful for working in multiple environments.

    Example::

       [like(request.getNormalizedParams().getHttpHost(), '*stage.domain.com')]
            plugin.tx_typo3docchecklogin_doccheckauthentication.settings.loginOverrideId = 1111111111
       [global]


