import { useState } from "react";
import { Button } from "../ui/button";
import { Input } from "../ui/input";
import { Card } from "../ui/card";
import { Label } from "../ui/label";
import { Briefcase, Mail, Lock, User, Building, Eye, EyeOff } from "lucide-react";

interface RegisterPageProps {
  onRegister: () => void;
  onSwitchToLogin: () => void;
}

export function RegisterPage({ onRegister, onSwitchToLogin }: RegisterPageProps) {
  const [formData, setFormData] = useState({
    fullName: "",
    organization: "",
    email: "",
    password: "",
    confirmPassword: ""
  });
  const [showPassword, setShowPassword] = useState(false);
  const [isLoading, setIsLoading] = useState(false);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    
    if (formData.password !== formData.confirmPassword) {
      alert("Passwords don't match!");
      return;
    }

    setIsLoading(true);
    
    // Simulate API call
    setTimeout(() => {
      setIsLoading(false);
      onRegister();
    }, 1000);
  };

  const handleChange = (field: string, value: string) => {
    setFormData(prev => ({ ...prev, [field]: value }));
  };

  return (
    <div className="min-h-screen bg-gradient-to-br from-blue-50 via-indigo-50 to-purple-50 flex items-center justify-center p-4">
      <div className="w-full max-w-md">
        {/* Logo/Brand */}
        <div className="text-center mb-8">
          <div className="inline-flex items-center justify-center w-16 h-16 bg-gradient-to-br from-blue-600 to-blue-700 rounded-2xl mb-4">
            <Briefcase className="w-9 h-9 text-white" />
          </div>
          <h1 className="text-3xl font-semibold text-gray-900 mb-2">TightShip Workspace</h1>
          <p className="text-gray-600">Create your workspace in seconds</p>
        </div>

        {/* Register Form */}
        <Card className="p-8 bg-white border border-gray-200 shadow-xl">
          <div className="mb-6">
            <h2 className="text-xl font-semibold text-gray-900 mb-1">Create account</h2>
            <p className="text-sm text-gray-600">Start managing your documents with zero chaos</p>
          </div>

          <form onSubmit={handleSubmit} className="space-y-4">
            {/* Full Name */}
            <div className="space-y-2">
              <Label htmlFor="fullName">Full Name</Label>
              <div className="relative">
                <User className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
                <Input
                  id="fullName"
                  type="text"
                  placeholder="Jane Doe"
                  value={formData.fullName}
                  onChange={(e) => handleChange("fullName", e.target.value)}
                  className="pl-9"
                  required
                />
              </div>
            </div>

            {/* Organization */}
            <div className="space-y-2">
              <Label htmlFor="organization">Organization</Label>
              <div className="relative">
                <Building className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
                <Input
                  id="organization"
                  type="text"
                  placeholder="Your Company"
                  value={formData.organization}
                  onChange={(e) => handleChange("organization", e.target.value)}
                  className="pl-9"
                  required
                />
              </div>
            </div>

            {/* Email */}
            <div className="space-y-2">
              <Label htmlFor="email">Work Email</Label>
              <div className="relative">
                <Mail className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
                <Input
                  id="email"
                  type="email"
                  placeholder="your.email@company.com"
                  value={formData.email}
                  onChange={(e) => handleChange("email", e.target.value)}
                  className="pl-9"
                  required
                />
              </div>
            </div>

            {/* Password */}
            <div className="space-y-2">
              <Label htmlFor="password">Password</Label>
              <div className="relative">
                <Lock className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
                <Input
                  id="password"
                  type={showPassword ? "text" : "password"}
                  placeholder="••••••••"
                  value={formData.password}
                  onChange={(e) => handleChange("password", e.target.value)}
                  className="pl-9 pr-9"
                  required
                />
                <button
                  type="button"
                  onClick={() => setShowPassword(!showPassword)}
                  className="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
                >
                  {showPassword ? (
                    <EyeOff className="w-4 h-4" />
                  ) : (
                    <Eye className="w-4 h-4" />
                  )}
                </button>
              </div>
              <p className="text-xs text-gray-500">Must be at least 8 characters</p>
            </div>

            {/* Confirm Password */}
            <div className="space-y-2">
              <Label htmlFor="confirmPassword">Confirm Password</Label>
              <div className="relative">
                <Lock className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
                <Input
                  id="confirmPassword"
                  type={showPassword ? "text" : "password"}
                  placeholder="••••••••"
                  value={formData.confirmPassword}
                  onChange={(e) => handleChange("confirmPassword", e.target.value)}
                  className="pl-9"
                  required
                />
              </div>
            </div>

            {/* Terms */}
            <div className="flex items-start gap-2">
              <input
                type="checkbox"
                id="terms"
                className="mt-1 rounded border-gray-300"
                required
              />
              <label htmlFor="terms" className="text-xs text-gray-600">
                I agree to the{" "}
                <a href="#" className="text-blue-600 hover:text-blue-700">Terms of Service</a>
                {" "}and{" "}
                <a href="#" className="text-blue-600 hover:text-blue-700">Privacy Policy</a>
              </label>
            </div>

            {/* Submit */}
            <Button
              type="submit"
              className="w-full bg-blue-600 hover:bg-blue-700"
              disabled={isLoading}
            >
              {isLoading ? "Creating account..." : "Create account"}
            </Button>
          </form>

          {/* Login Link */}
          <div className="mt-6 text-center text-sm">
            <span className="text-gray-600">Already have an account? </span>
            <button
              type="button"
              onClick={onSwitchToLogin}
              className="text-blue-600 hover:text-blue-700 font-medium"
            >
              Sign in
            </button>
          </div>
        </Card>

        {/* Features */}
        <div className="mt-8 grid grid-cols-3 gap-4 text-center">
          <div>
            <div className="text-2xl mb-1">📁</div>
            <div className="text-xs text-gray-600">Auto-routing</div>
          </div>
          <div>
            <div className="text-2xl mb-1">🔄</div>
            <div className="text-xs text-gray-600">Version Control</div>
          </div>
          <div>
            <div className="text-2xl mb-1">✅</div>
            <div className="text-xs text-gray-600">Status Workflow</div>
          </div>
        </div>

        {/* Footer */}
        <div className="mt-8 text-center text-xs text-gray-500">
          <p>© 2025 TightShip Workspace. All rights reserved.</p>
        </div>
      </div>
    </div>
  );
}
