# Consumer compatibility fixtures

`api-v2.1.json` records public and protected method contracts from the original
committed source at `8e2c0e8`, before the pending refactor. It includes method
visibility, static/instance dispatch, native return declarations, parameter names,
types, references and defaults. Inherited internal SPL methods are compared with
the executing runtime separately, because their contracts vary by PHP version.

Keep this fixture frozen for compatible updates. Do not regenerate it from the
implementation being tested to make a failing compatibility check pass.

`CopyableParent` and `MutableCollection` model consumer-defined inherited state
and collection protocols. Their tests check preservation of classes, values,
serialization, public interfaces and isolation from input mutations.
