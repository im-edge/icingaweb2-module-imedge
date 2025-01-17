%define revision 1
%define git_version %( git describe --tags | cut -c2- | tr -s '-' '+')
%define git_hash %( git rev-parse --short HEAD )
%define basedir         %{_datadir}/icingaweb2/modules/imedge
%define bindir          %{_bindir}
%undefine __brp_mangle_shebangs

Name:           icingaweb2-module-imedge
Version:        %{git_version}
Release:        %{revision}%{?dist}
Summary:        IMEdge web module for Icinga
Group:          Applications/System
License:        MIT
URL:            https://github.com/im-edge
Source0:        https://github.com/im-edge/icingaweb2-module-imedge/archive/%{git_hash}.tar.gz
BuildArch:      noarch
BuildRoot:      %{_tmppath}/%{name}-%{git_version}-%{release}
Packager:       Thomas Gelf <thomas@gelf.net>
Requires:       icingaweb2

%description
IMEdge web module for Icinga

%prep

%build

%install
rm -rf %{buildroot}
mkdir -p %{buildroot}
mkdir -p %{buildroot}%{basedir}
cd - # ???
cp -pr  application library public vendor configuration.php run.php %{buildroot}%{basedir}/

%clean
rm -rf %{buildroot}

%files
%defattr(-,root,root)
%{basedir}

%changelog
* Mon Jan 13 2025 Thomas Gelf <thomas@gelf.net> 0.0.0
- Initial packaging
