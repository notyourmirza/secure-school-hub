# Windows client build

Build on a Windows 10/11 x64 machine with the .NET 8 SDK:

```powershell
dotnet restore SchoolSaaS.Desktop.csproj
dotnet publish SchoolSaaS.Desktop.csproj -c Release -r win-x64 --self-contained true -p:PublishSingleFile=false -o .\publish\win-x64
```

The published directory contains the self-contained application and does not require a separate .NET runtime installation. The installer should package that directory, create a Start Menu shortcut and desktop shortcut, and register an uninstaller. An installer project can be added with WiX or Inno Setup on the Windows build machine; the API base URL should be moved to an appsettings file before release.

The client keeps offline operations in SQLite. Every queued operation has a client UUID, so the API can safely implement idempotent push handling when connectivity returns.
