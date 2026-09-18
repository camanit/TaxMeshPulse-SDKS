from setuptools import setup, find_packages

setup(
    name="taxmeshpulse",
    version="1.0.0",
    description="Official Python SDK for TaxMeshPulse (TMP) - Unified Tax-as-a-Service & DJP Coretax Engine",
    author="CTARTech Ecosystem",
    author_email="dev@ctar.tech",
    url="https://taxmeshpulse.ctar.tech",
    packages=find_packages(),
    py_modules=["taxmeshpulse"],
    python_requires=">=3.8",
    classifiers=[
        "Programming Language :: Python :: 3",
        "License :: OSI Approved :: MIT License",
        "Operating System :: OS Independent",
    ],
)
