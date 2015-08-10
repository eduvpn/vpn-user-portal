%global github_owner     fkooman
%global github_name      vpn-user-portal

Name:       vpn-user-portal
Version:    1.0.1
Release:    1%{?dist}
Summary:    Portal to manage OpenVPN client configurations

Group:      Applications/Internet
License:    ASL-2.0
URL:        https://github.com/%{github_owner}/%{github_name}
Source0:    https://github.com/%{github_owner}/%{github_name}/archive/%{version}.tar.gz
Source1:    vpn-user-portal-httpd-conf
Source2:    vpn-user-portal-autoload.php

BuildArch:  noarch

Requires:   php(language) >= 5.4
Requires:   php-pcre
Requires:   php-pdo
Requires:   php-zip

Requires:   httpd

Requires:   php-composer(fkooman/ini) >= 1.0.0
Requires:   php-composer(fkooman/ini) < 2.0.0
Requires:   php-composer(fkooman/rest) >= 1.0.0
Requires:   php-composer(fkooman/rest) < 2.0.0
Requires:   php-composer(fkooman/rest-plugin-authentication-mellon) >= 1.0.0
Requires:   php-composer(fkooman/rest-plugin-authentication-mellon) < 2.0.0
Requires:   php-composer(fkooman/tpl-twig) >= 1.0.0
Requires:   php-composer(fkooman/tpl-twig) < 2.0.0

Requires:   php-composer(guzzlehttp/guzzle) >= 5.3
Requires:   php-composer(guzzlehttp/guzzle) < 6.0
Requires:   php-pear(pear.symfony.com/ClassLoader) >= 2.3.9
Requires:   php-pear(pear.symfony.com/ClassLoader) < 3.0

Requires(post): policycoreutils-python
Requires(postun): policycoreutils-python

%description
This project provides a user interface for managing OpenVPN configurations 
using the vpn-cert-service software.

%prep
%setup -qn %{github_name}-%{version}

sed -i "s|dirname(__DIR__)|'%{_datadir}/vpn-user-portal'|" bin/vpn-user-portal-init

%build

%install
# Apache configuration
install -m 0644 -D -p %{SOURCE1} ${RPM_BUILD_ROOT}%{_sysconfdir}/httpd/conf.d/vpn-user-portal.conf

# Application
mkdir -p ${RPM_BUILD_ROOT}%{_datadir}/vpn-user-portal
cp -pr web views src ${RPM_BUILD_ROOT}%{_datadir}/vpn-user-portal

# use our own class loader
mkdir -p ${RPM_BUILD_ROOT}%{_datadir}/vpn-user-portal/vendor
cp -pr %{SOURCE2} ${RPM_BUILD_ROOT}%{_datadir}/vpn-user-portal/vendor/autoload.php

mkdir -p ${RPM_BUILD_ROOT}%{_bindir}
cp -pr bin/* ${RPM_BUILD_ROOT}%{_bindir}

# Config
mkdir -p ${RPM_BUILD_ROOT}%{_sysconfdir}/vpn-user-portal
cp -p config/config.ini.defaults ${RPM_BUILD_ROOT}%{_sysconfdir}/vpn-user-portal/config.ini
ln -s ../../../etc/vpn-user-portal ${RPM_BUILD_ROOT}%{_datadir}/vpn-user-portal/config

# Data
mkdir -p ${RPM_BUILD_ROOT}%{_localstatedir}/lib/vpn-user-portal

%post
semanage fcontext -a -t httpd_sys_rw_content_t '%{_localstatedir}/lib/vpn-user-portal(/.*)?' 2>/dev/null || :
restorecon -R %{_localstatedir}/lib/vpn-user-portal || :

%postun
if [ $1 -eq 0 ] ; then  # final removal
semanage fcontext -d -t httpd_sys_rw_content_t '%{_localstatedir}/lib/vpn-user-portal(/.*)?' 2>/dev/null || :
fi

%files
%defattr(-,root,root,-)
%config(noreplace) %{_sysconfdir}/httpd/conf.d/vpn-user-portal.conf
%dir %attr(-,apache,apache) %{_sysconfdir}/vpn-user-portal
%config(noreplace) %attr(0600,apache,apache) %{_sysconfdir}/vpn-user-portal/config.ini
%{_bindir}/vpn-user-portal-init
%dir %{_datadir}/vpn-user-portal
%{_datadir}/vpn-user-portal/src
%{_datadir}/vpn-user-portal/vendor
%{_datadir}/vpn-user-portal/web
%{_datadir}/vpn-user-portal/views
%{_datadir}/vpn-user-portal/config
%dir %attr(0700,apache,apache) %{_localstatedir}/lib/vpn-user-portal
%doc README.md composer.json config/config.ini.defaults
%license COPYING

%changelog
* Mon Aug 10 2015 François Kooman <fkooman@tuxed.net> - 1.0.1-1
- update to 1.0.1

* Mon Jul 20 2015 François Kooman <fkooman@tuxed.net> - 1.0.0-1
- initial release
