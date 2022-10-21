.. include:: /Includes.rst.txt

.. _configuration-tsconfig:

==================
TsConfig
==================

This site covers options to configure the plugin.

.. _configuration-custom-template:

Custom view templates
====================

.. confval:: templateLayouts

    :type: array

    You might want to change the HTML code that renders the iFrame.

    To do so, copy the :file:`Resources/Private/Templates/DocCheckAuthentication/` and :file:`Resources/Private/Partials/DocCheckAuthentication/` folder to, for example, your :file:`<yourPathOfChoice>/Templates/DocCheckAuthentication` and set the following in your TYPO3 setup:

    Example::

      plugin.tx_typo3docchecklogin.view.templateRootPath.10 = <yourPathOfChoice>/Templates/DocCheckAuthentication
      plugin.tx_typo3docchecklogin.view.partialRootPaths.10 = <yourPathOfChoice>/Partials/DocCheckAuthentication


.. _configuration-overwrite-loginid:

Overwrite login Id
====================

.. confval:: loginOverrideId

    :type: string
    :Default: null

    This numeric parameter overrides the used DocCheck Login id. Especially useful for working in multiple environments.

    Example::

       [like(request.getNormalizedParams().getHttpHost(), '*stage.domain.com')]
            plugin.tx_typo3docchecklogin.settings.loginOverrideId = 1111111111
       [global]


