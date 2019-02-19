#!/usr/bin/env python
# -*- coding: utf-8 -*-

import os
import sys

here = lambda *a: os.path.join(os.path.dirname(__file__), *a)


try:
    from setuptools import setup
except ImportError:
    from distutils.core import setup

if sys.argv[-1] == 'publish':
    os.system('python setup.py sdist upload')
    sys.exit()

readme = open(here('README.md')).read()
requirements = [x.strip() for x in open(here('requirements.txt')).readlines()]

setup(
    name='pyharmony',
    version='0.1.0',
    description='Open source control for Belkin WeMo devices',
    long_description=readme,
    author='Cory Sitko',
    author_email='csitko@gmail.com',
    url='https://github.com/csitko/pyharmony',
    packages=[
        'harmony',
    ],
    package_dir={'harmony': 'harmony'},
    include_package_data=True,
    install_requires=requirements,
    license="BSD",
    zip_safe=False,
    keywords='pyharmony',
    classifiers=[
        'Development Status :: 2 - Pre-Alpha',
        'Topic :: Home Automation',
        'Intended Audience :: Developers',
        'License :: OSI Approved :: BSD License',
        'Natural Language :: English',
        "Programming Language :: Python :: 2",
        'Programming Language :: Python :: 2.6',
        'Programming Language :: Python :: 2.7',
        'Programming Language :: Python :: 3',
        'Programming Language :: Python :: 3.3',
    ],
    entry_points={
        'console_scripts': [
            'harmony = harmony.__main__:main'
        ]
    },
)
